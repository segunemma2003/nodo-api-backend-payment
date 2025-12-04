# Heroku Deployment Guide for Nodopay API

## Prerequisites

1. Heroku account (sign up at https://heroku.com)
2. Heroku CLI installed (https://devcenter.heroku.com/articles/heroku-cli)
3. Git repository initialized
4. AWS account with S3 bucket configured (for file storage)

## Step 1: Install Heroku CLI

```bash
# macOS
brew tap heroku/brew && brew install heroku

# Or download from https://devcenter.heroku.com/articles/heroku-cli
```

## Step 2: Login to Heroku

```bash
heroku login
```

## Step 3: Create Heroku App

```bash
# Create app
heroku create nodopay-api

# Or create with specific region
heroku create nodopay-api --region us
```

## Step 4: Add PostgreSQL Database

```bash
# Add PostgreSQL addon (free tier)
heroku addons:create heroku-postgresql:essential-0

# Verify database
heroku pg:info
```

## Step 5: Configure Environment Variables

```bash
# Set application key (will be generated automatically on first deploy)
heroku config:set APP_KEY=$(php artisan key:generate --show)

# Set application environment
heroku config:set APP_ENV=production
heroku config:set APP_DEBUG=false

# Set database connection (automatically set by Heroku Postgres addon)
# DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD are auto-set

# Set queue connection
heroku config:set QUEUE_CONNECTION=database

# Set filesystem disk
heroku config:set FILESYSTEM_DISK=s3

# Configure AWS S3
heroku config:set AWS_ACCESS_KEY_ID=your_access_key_id
heroku config:set AWS_SECRET_ACCESS_KEY=your_secret_access_key
heroku config:set AWS_DEFAULT_REGION=us-east-1
heroku config:set AWS_BUCKET=your-bucket-name

# Configure Mail (use your SMTP provider)
heroku config:set MAIL_MAILER=smtp
heroku config:set MAIL_HOST=smtp.mailtrap.io
heroku config:set MAIL_PORT=587
heroku config:set MAIL_USERNAME=your_mail_username
heroku config:set MAIL_PASSWORD=your_mail_password
heroku config:set MAIL_ENCRYPTION=tls
heroku config:set MAIL_FROM_ADDRESS=noreply@nodopay.com
heroku config:set MAIL_FROM_NAME="Nodopay"

# Set log level
heroku config:set LOG_CHANNEL=errorlog
```

## Step 6: Update composer.json for Heroku

The composer.json already has the correct PHP version. Ensure it includes:

```json
{
  "require": {
    "php": "^8.2",
    "ext-pdo": "*",
    "ext-pdo_pgsql": "*"
  }
}
```

## Step 7: Deploy to Heroku

```bash
# Initialize git if not already done
git init
git add .
git commit -m "Initial commit"

# Add Heroku remote
heroku git:remote -a nodopay-api

# Deploy
git push heroku main

# Or if using master branch
git push heroku master
```

## Step 8: Run Migrations

```bash
# Run migrations
heroku run php artisan migrate --force

# Create storage link
heroku run php artisan storage:link
```

## Step 9: Scale Dynos

```bash
# Scale web dyno
heroku ps:scale web=1

# Scale worker dyno (for queue processing)
heroku ps:scale worker=1
```

## Step 10: Set Up Scheduler (Optional)

For scheduled tasks like repayment reminders:

```bash
# Add scheduler addon
heroku addons:create scheduler:standard

# Open scheduler dashboard
heroku addons:open scheduler
```

In the scheduler dashboard, add:
- **Command**: `php artisan schedule:run`
- **Frequency**: Every 10 minutes

Or add to `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('inspire')->hourly();
    // Add your scheduled tasks here
}
```

## Step 11: Verify Deployment

```bash
# Check app status
heroku ps

# View logs
heroku logs --tail

# Test API endpoint
curl https://nodopay-api.herokuapp.com/api/up
```

## Step 12: Configure Custom Domain (Optional)

```bash
# Add custom domain
heroku domains:add api.nodopay.com

# Verify DNS
heroku domains
```

## Important Notes

### Database
- Heroku automatically provides PostgreSQL
- Database credentials are in `DATABASE_URL` config var
- Use `heroku pg:psql` to access database

### File Storage
- All files should be stored in S3 (configured via FILESYSTEM_DISK)
- Local storage is ephemeral on Heroku

### Queue Workers
- Worker dyno processes queued jobs
- Ensure worker is scaled: `heroku ps:scale worker=1`
- Monitor with: `heroku logs --ps worker`

### Logs
- View logs: `heroku logs --tail`
- Logs are stored for 1500 lines (free tier)
- Use log drain addon for extended logging

### Environment Variables
- View all: `heroku config`
- Set: `heroku config:set KEY=value`
- Unset: `heroku config:unset KEY`

## Troubleshooting

### Migration Errors
```bash
# Reset database (WARNING: deletes all data)
heroku pg:reset DATABASE_URL
heroku run php artisan migrate --force
```

### Queue Not Processing
```bash
# Check worker status
heroku ps

# Restart worker
heroku restart worker

# View worker logs
heroku logs --ps worker --tail
```

### Storage Issues
```bash
# Recreate storage link
heroku run php artisan storage:link

# Clear cache
heroku run php artisan cache:clear
heroku run php artisan config:clear
```

### Performance
- Use Heroku Metrics to monitor performance
- Consider upgrading dyno size if needed
- Enable query logging for debugging

## Maintenance Commands

```bash
# Run artisan commands
heroku run php artisan [command]

# Access database
heroku pg:psql

# Restart app
heroku restart

# View config
heroku config

# Open app in browser
heroku open
```

## Cost Estimation

**Free Tier (Hobby):**
- Web dyno: 550 hours/month (free)
- Worker dyno: 550 hours/month (free)
- PostgreSQL: 10,000 rows (free)
- Scheduler: Free

**Production (Standard):**
- Web dyno: $7/month per dyno
- Worker dyno: $7/month per dyno
- PostgreSQL Essential: $5/month
- Scheduler: Free

## Security Checklist

- [ ] Set APP_DEBUG=false
- [ ] Use strong APP_KEY
- [ ] Configure CORS properly
- [ ] Use HTTPS (automatic on Heroku)
- [ ] Secure S3 credentials
- [ ] Use environment variables for secrets
- [ ] Enable Heroku Shield for compliance (if needed)

## Support

For issues:
- Heroku Status: https://status.heroku.com
- Heroku Support: https://help.heroku.com
- Laravel Docs: https://laravel.com/docs

