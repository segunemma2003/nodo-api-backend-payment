# How to Retrieve Paystack Secret Key from Heroku

## Method 1: Using Heroku CLI (Recommended)

### Step 1: Install Heroku CLI (if not already installed)
```bash
# macOS
brew tap heroku/brew && brew install heroku

# Or download from: https://devcenter.heroku.com/articles/heroku-cli
```

### Step 2: Login to Heroku
```bash
heroku login
```

### Step 3: Navigate to your project directory
```bash
cd /Users/mac/Desktop/desktop2/untitled_folder_2/newnodoapp
```

### Step 4: Retrieve the Paystack Secret Key
```bash
# Get the Paystack secret key
heroku config:get PAYSTACK_SECRET_KEY

# Or get all environment variables (to see both keys)
heroku config

# Get both Paystack keys at once
heroku config:get PAYSTACK_SECRET_KEY PAYSTACK_PUBLIC_KEY
```

### Step 5: View in Heroku Dashboard (Alternative)
1. Go to [Heroku Dashboard](https://dashboard.heroku.com)
2. Select your app: `nodopay-api-0fbd4546e629`
3. Go to **Settings** tab
4. Click **Reveal Config Vars**
5. Look for `PAYSTACK_SECRET_KEY` and `PAYSTACK_PUBLIC_KEY`

## Method 2: Using Heroku CLI with App Name

If you know your Heroku app name:
```bash
heroku config:get PAYSTACK_SECRET_KEY --app nodopay-api-0fbd4546e629
```

## Method 3: Set/Update the Key on Heroku

If you need to set or update the key:
```bash
# Set the secret key
heroku config:set PAYSTACK_SECRET_KEY=sk_live_xxxxxxxxxxxxx --app nodopay-api-0fbd4546e629

# Set the public key
heroku config:set PAYSTACK_PUBLIC_KEY=pk_live_xxxxxxxxxxxxx --app nodopay-api-0fbd4546e629

# Set both at once
heroku config:set PAYSTACK_SECRET_KEY=sk_live_xxxxxxxxxxxxx PAYSTACK_PUBLIC_KEY=pk_live_xxxxxxxxxxxxx --app nodopay-api-0fbd4546e629
```

## Method 4: Check if Key is Set (via API)

You can also check if the key is configured by calling the status endpoint:
```bash
curl https://nodopay-api-0fbd4546e629.herokuapp.com/api/paystack/status
```

This will return:
```json
{
  "configured": true,
  "secret_key_set": true,
  "public_key_set": true,
  "message": "Paystack is properly configured"
}
```

## Important Notes

1. **Security**: Never commit secret keys to version control
2. **Test vs Live Keys**: 
   - Test keys start with `sk_test_` and `pk_test_`
   - Live keys start with `sk_live_` and `pk_live_`
3. **After Setting Keys**: The app will automatically use them - no restart needed for Laravel config
4. **Verification**: Use the `/api/paystack/status` endpoint to verify configuration

## Quick Command Reference

```bash
# Get secret key
heroku config:get PAYSTACK_SECRET_KEY

# Get public key
heroku config:get PAYSTACK_PUBLIC_KEY

# Get all config vars
heroku config

# Set secret key
heroku config:set PAYSTACK_SECRET_KEY=your_key_here

# Set public key
heroku config:set PAYSTACK_PUBLIC_KEY=your_key_here
```
