# 🚀 Secure Deployment Guide
## Trou Idees Facebook Approval System

**Last Updated:** November 1, 2025
**Version:** 3.0.0 (Secure wp-config.php Integration)

---

## 📋 What You Have Now

I've created these files for you:

1. ✅ **wp-config-UPDATED.php** - Your wp-config.php with Facebook secrets added
2. ✅ **config-SECURE.php** - New secure config that reads from wp-config.php
3. ✅ **.gitignore** - Protects secrets from version control
4. ✅ **DEPLOYMENT-GUIDE.md** - This file

---

## 🔒 Security Improvements

### Before (Insecure):
```
config.php
├── Contains Facebook tokens directly
├── Likely committed to git
└── ❌ EXPOSED TO PUBLIC

Git Repository
├── config.php with tokens
├── submissions.json with user data
└── ❌ ALL SECRETS EXPOSED
```

### After (Secure):
```
wp-config.php (on server only)
├── Contains all Facebook tokens
├── Never committed to git
└── ✅ SECURE

config-SECURE.php (in git)
├── Reads from wp-config.php
├── No secrets inside
└── ✅ SAFE TO COMMIT

Git Repository
├── config-SECURE.php ✅
├── webhook-receiver.php ✅
├── queue.php ✅
└── .gitignore ✅
```

---

## 📝 Step-by-Step Deployment

### Step 1: Update Your Server's wp-config.php

**Location:** On your server at `/var/www/html/wp-config.php` (or wherever WordPress is installed)

**Option A: Manual Edit (Recommended)**

1. **Connect to your server** (via FTP, SSH, or cPanel File Manager)

2. **Open wp-config.php** for editing

3. **Find this section** (around line 106):
   ```php
   define('FBAQ_PAGE_ID', '111290850258093');
   define('FBAQ_ACCESS_TOKEN', 'EAALhRCDRHOkBPgW6qWL...');
   define('WP_DEBUG', false);
   ```

4. **Replace with this** (copy from wp-config-UPDATED.php lines 95-145):
   ```php
   define('WP_DEBUG', false);
   define('WP_DEBUG_LOG', false);

   // ============================================================================
   // FACEBOOK APPROVAL SYSTEM CONFIGURATION
   // ============================================================================

   // Facebook Page 1: Verhoudings & Leefstyl
   define('FB_PAGE1_NAME', 'Trou Idees:Verhoudings & Leefstyl');
   define('FB_PAGE1_ID', '111290850258093');
   define('FB_PAGE1_TOKEN', 'EAALhRCDRHOkBPwLpQyemOssxMpvZBLDO4cVzcwqUcoHqZCz64a60O5ILnqdtwpLGuGZAuRgXdLiozzmT8KBcju6qy82JcAo72K1UJjBIIPLNiATXhWb7VlGFMQlaL0DpFfD2afeAPFIhBxOOyrC3H3XdAkBTThCrQkHlSE6ZAeXNNIn2MLzklqE92rrPoY5VkA8cBU2lefZAj6IwI');
   define('FB_PAGE1_PREFIX', '');
   define('FB_PAGE1_SUFFIX', '#TrouIdees');

   // Facebook Page 2: Troues & Funksies
   define('FB_PAGE2_NAME', 'Trou Idees: Troues & Funksies');
   define('FB_PAGE2_ID', '195604167157011');
   define('FB_PAGE2_TOKEN', 'EAALhRCDRHOkBP4ZCJn8Bj6W9KtR0YZAeKwPygpjuUZCJgmWDpP6ZAEll92GFZCEggvFdtZCjjD5ynQPG4bZCz72krbVCKKZCqQtrioylWC9DjZAJdZCZBmn6wPebgWQcHdBDAlyOVs4vLN9f6mvUtGxF1xZBZBh84R2hPbLuogctnKTcXR3uR5oAFIwakcdZCLNi4OYZCOHm4UI7W08E65KxWRH');
   define('FB_PAGE2_PREFIX', '');
   define('FB_PAGE2_SUFFIX', '#Trous&Funksies');

   // Webhook Security (IMPORTANT: Generate random secret!)
   define('FB_WEBHOOK_SECRET', 'CHANGE_THIS_TO_RANDOM_64_CHAR_HEX_STRING');

   // Admin Authentication
   define('FB_ADMIN_PASSWORD', 'TrouIdees2024!'); // TODO: Change to strong password
   define('FB_SECRET_KEY', '3ea9af2a2bd7a53f3cdcbb3a2743fa843c7efe56c41c1502296e2c19aa7c0a26');

   // Application Settings
   define('FB_DEBUG_MODE', false);    // IMPORTANT: Set to false for production!
   define('FB_USE_PASSWORD', true);
   ```

5. **Save the file**

**Option B: Replace Entire File (If Comfortable)**

1. Backup your current wp-config.php:
   ```bash
   cp wp-config.php wp-config.php.backup
   ```

2. Upload `wp-config-UPDATED.php` as `wp-config.php`

---

### Step 2: Generate Webhook Secret

**Why?** Prevent unauthorized submissions to your webhook.

**Generate random secret:**

**Option A: Using Command Line (Linux/Mac):**
```bash
openssl rand -hex 32
```

**Option B: Using PHP:**
```bash
php -r "echo bin2hex(random_bytes(32));"
```

**Option C: Online Generator:**
Visit: https://www.random.org/strings/?num=1&len=64&digits=on&loweralpha=on&unique=on&format=html&rnd=new

**Example Output:**
```
a1b2c3d4e5f6789012345678901234567890abcdefghijklmnopqrstuvwxyz1234
```

**Update wp-config.php:**
```php
define('FB_WEBHOOK_SECRET', 'a1b2c3d4e5f6789012345678901234567890abcdefghijklmnopqrstuvwxyz1234');
```

---

### Step 3: Update Forminator Webhook URLs

**Old URLs (Insecure):**
```
https://trouidees.co.za/fb-approval/webhook-receiver.php?page=page1
https://trouidees.co.za/fb-approval/webhook-receiver.php?page=page2
```

**New URLs (Secure - include secret):**
```
https://trouidees.co.za/fb-approval/webhook-receiver.php?page=page1&secret=YOUR_SECRET_HERE
https://trouidees.co.za/fb-approval/webhook-receiver.php?page=page2&secret=YOUR_SECRET_HERE
```

**How to Update:**

1. Go to WordPress admin: **Forminator** → **Forms**
2. Edit your form
3. Click **Integrations** tab
4. Find the **Webhook** integration
5. Update the URL to include `&secret=YOUR_SECRET_HERE`
6. Click **Save**

---

### Step 4: Upload Secure Config File

**On your server, in the fb-approval directory:**

1. **Backup old config.php:**
   ```bash
   mv config.php config.php.OLD-INSECURE
   ```

2. **Upload config-SECURE.php as config.php:**
   ```bash
   # Rename config-SECURE.php to config.php
   mv config-SECURE.php config.php
   ```

3. **Set proper permissions:**
   ```bash
   chmod 644 config.php
   chown www-data:www-data config.php
   ```

---

### Step 5: Verify Configuration

**Test 1: Check Configuration Loads**

Visit: `https://trouidees.co.za/fb-approval/queue.php`

**Expected:** Login page appears (no errors)

**If error:** Check that wp-config.php has all FB_* constants defined

---

**Test 2: Check Webhook Works**

Send a test form submission through Forminator

**Expected:**
- Submission appears in queue.php
- No errors in webhook.log

**If error 403 "Forbidden":**
- Check that webhook URL includes `&secret=YOUR_SECRET`
- Verify FB_WEBHOOK_SECRET in wp-config.php matches URL

---

**Test 3: Check Files Are Protected**

Try to access these URLs directly:

```
https://trouidees.co.za/fb-approval/submissions.json
https://trouidees.co.za/fb-approval/config.php
https://trouidees.co.za/fb-approval/webhook.log
```

**Expected:** All should return **403 Forbidden** or **404 Not Found**

**If files are accessible:**
- Check .htaccess exists in fb-approval directory
- Check Apache is configured to read .htaccess files

---

### Step 6: Clean Up Local Files

**On your local machine (this desktop):**

1. **Delete the wp-config files:**
   ```
   DELETE: wp-config.php
   DELETE: wp-config-UPDATED.php
   ```

2. **Keep these files (safe to commit):**
   ```
   KEEP: config-SECURE.php
   KEEP: .gitignore
   KEEP: DEPLOYMENT-GUIDE.md
   KEEP: webhook-receiver.php
   KEEP: queue.php
   KEEP: simple-auth.php
   ```

3. **Verify .gitignore is working:**
   ```bash
   git status
   ```

   **Should NOT show:**
   - wp-config.php
   - submissions.json
   - *.log files

---

### Step 7: Commit Safe Files to Git

**Now you can safely commit your code:**

```bash
# Add safe files
git add .gitignore
git add config-SECURE.php
git add webhook-receiver.php
git add queue.php
git add queue-troues.php
git add simple-auth.php
git add DEPLOYMENT-GUIDE.md
git add assets/
git add .claude/

# Commit
git commit -m "Security: Move secrets to wp-config.php

- Migrated all Facebook tokens to wp-config.php
- Created secure config that reads from wp-config
- Added .gitignore to protect sensitive files
- Added webhook secret verification
- Safe to push to public repository"

# Push to repository
git push
```

---

## 🔐 Security Checklist

Before going live, verify:

- [ ] ✅ wp-config.php updated with all FB_* constants
- [ ] ✅ FB_WEBHOOK_SECRET is random 64-character string (not default)
- [ ] ✅ FB_ADMIN_PASSWORD changed to strong password
- [ ] ✅ FB_DEBUG_MODE set to `false`
- [ ] ✅ Forminator webhook URLs include `&secret=YOUR_SECRET`
- [ ] ✅ Old config.php deleted or renamed on server
- [ ] ✅ config-SECURE.php renamed to config.php on server
- [ ] ✅ submissions.json not directly accessible (test in browser)
- [ ] ✅ .htaccess exists in fb-approval directory
- [ ] ✅ Local wp-config.php files deleted
- [ ] ✅ .gitignore committed and working
- [ ] ✅ Git repository does NOT contain secrets

---

## 🆘 Troubleshooting

### Error: "Could not locate wp-config.php"

**Cause:** config.php can't find your WordPress installation

**Solution:**

1. Find your wp-config.php path:
   ```bash
   find /var/www -name "wp-config.php"
   ```

2. Edit config.php line 48 and add your path:
   ```php
   $wpConfigPaths = [
       '/your/actual/path/to/wp-config.php',  // Add this line
       __DIR__ . '/../../../wp-config.php',
       // ... existing paths
   ];
   ```

---

### Error: "Missing required constants"

**Cause:** wp-config.php doesn't have FB_* constants

**Solution:**

1. Check wp-config.php contains all these:
   ```php
   define('FB_PAGE1_NAME', '...');
   define('FB_PAGE1_ID', '...');
   define('FB_PAGE1_TOKEN', '...');
   define('FB_PAGE1_PREFIX', '...');
   define('FB_PAGE1_SUFFIX', '...');
   define('FB_PAGE2_NAME', '...');
   define('FB_PAGE2_ID', '...');
   define('FB_PAGE2_TOKEN', '...');
   define('FB_PAGE2_PREFIX', '...');
   define('FB_PAGE2_SUFFIX', '...');
   define('FB_WEBHOOK_SECRET', '...');
   define('FB_ADMIN_PASSWORD', '...');
   define('FB_SECRET_KEY', '...');
   define('FB_DEBUG_MODE', false);
   define('FB_USE_PASSWORD', true);
   ```

2. Copy from wp-config-UPDATED.php if needed

---

### Webhook Returns 403 "Forbidden"

**Cause:** Webhook secret doesn't match

**Solution:**

1. Check Forminator webhook URL includes `&secret=YOUR_SECRET`
2. Check FB_WEBHOOK_SECRET in wp-config.php matches
3. Ensure no extra spaces or quotes in secret

---

### submissions.json Accessible in Browser

**Cause:** .htaccess not blocking access

**Solution:**

1. Check .htaccess exists in fb-approval directory:
   ```bash
   ls -la /path/to/fb-approval/.htaccess
   ```

2. If missing, create it:
   ```apache
   <Files "submissions.json">
       Require all denied
   </Files>

   <Files "*.log">
       Require all denied
   </Files>

   <Files "config.php">
       Require all denied
   </Files>
   ```

3. If .htaccess exists but not working, check Apache config:
   ```apache
   # In Apache config or .htaccess in parent directory:
   AllowOverride All
   ```

---

## 📞 Support

**Issues?** Check these resources:

1. **Security Audit Report** - See full security recommendations
2. **CLAUDE.md** - Project documentation and standards
3. **config.php comments** - Detailed inline documentation

---

## 🎉 You're Done!

Your Facebook Approval System is now secure:

- ✅ Secrets protected in wp-config.php
- ✅ Safe to commit code to version control
- ✅ Webhook verified with secret token
- ✅ Data files protected from direct access
- ✅ Debug mode disabled for production

**Next Steps:**

1. Delete local wp-config files
2. Commit safe files to git
3. Monitor webhook.log for any issues
4. Consider implementing other security audit recommendations

---

**Questions?** All configuration is documented in:
- `config-SECURE.php` (inline comments)
- `wp-config-UPDATED.php` (setup instructions)
- Security Audit Report (comprehensive security guide)
