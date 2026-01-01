# WordPress Plugin Directory Submission Guide

## Current Status: PREPARING FOR SUBMISSION

Last Updated: January 1, 2026

---

## Pre-Submission Checklist

### 1. Required Files
- [x] Main plugin file: `checkoutkeys.php` 
- [x] readme.txt with proper formatting
- [x] LICENSE file (GPL v2 or later)
- [x] .gitignore
- [x] All security fixes applied

### 2. Plugin Assets (Place in `.wordpress-org/` folder)

#### Banner Images
- **banner-772x250.png** (required)
  - Dimensions: 772x250 pixels
  - Format: PNG or JPG
  - Shows at top of plugin page
  
- **banner-1544x500.png** (optional, recommended)
  - Dimensions: 1544x500 pixels
  - Retina version of banner

#### Icon Images
- **icon-256x256.png** (required)
  - Dimensions: 256x256 pixels
  - Square format
  
- **icon-128x128.png** (required)
  - Dimensions: 128x128 pixels
  - Same design as 256x256

#### Screenshots
Add at least 3 screenshots showing:
1. **screenshot-1.png** - Dashboard overview with plan usage
2. **screenshot-2.png** - Settings page with API configuration
3. **screenshot-3.png** - License keys list or sync functionality

### 3. readme.txt Requirements ✓

Current readme.txt includes:
- [x] Plugin name and description
- [x] Tags (max 12)
- [x] Version compatibility
- [x] Installation instructions
- [x] FAQ section
- [x] Changelog
- [x] Screenshots section

### 4. Code Requirements

#### Security ✓
- [x] No direct file access (`defined('ABSPATH')`)
- [x] Sanitize all inputs
- [x] Escape all outputs
- [x] Use nonces for forms
- [x] Capability checks

#### WordPress Standards ✓
- [x] Uses WordPress coding standards
- [x] Proper text domain: 'checkoutkeys'
- [x] Translation ready
- [x] No external dependencies required

#### GPL Compatibility ✓
- [x] GPL v2 or later license
- [x] Compatible with 100% GPL
- [x] No proprietary code

### 5. Testing Checklist

Before submission, test:
- [ ] Fresh WordPress install (latest version)
- [ ] PHP 7.4, 8.0, 8.1, 8.2
- [ ] Plugin activation/deactivation
- [ ] No PHP errors or warnings
- [ ] No JavaScript console errors
- [ ] Settings save correctly
- [ ] API integration works
- [ ] Sync functionality works
- [ ] Responsive on mobile/tablet

### 6. SVN Repository Setup

WordPress uses SVN for plugin hosting. Structure:
```
/trunk/           - Current development version
/tags/            - Released versions (1.0.0, 1.0.1, etc.)
  /1.0.0/         - First release
/assets/          - Banner, icon, screenshots
```

### 7. Submission Process

1. **Apply for Plugin**
   - Go to https://wordpress.org/plugins/developers/add/
   - Submit your plugin ZIP file
   - Wait for review (usually 2-14 days)

2. **Initial Review**
   - WordPress team reviews code
   - Check for security issues
   - Verify GPL compatibility
   - Test basic functionality

3. **Receive SVN Access**
   - You'll get SVN repository URL
   - Format: `https://plugins.svn.wordpress.org/checkoutkeys/`

4. **First Commit**
   ```bash
   # Checkout SVN repo
   svn co https://plugins.svn.wordpress.org/checkoutkeys/
   
   # Add files to trunk
   cp -r /path/to/plugin/* checkoutkeys/trunk/
   
   # Add assets
   cp .wordpress-org/* checkoutkeys/assets/
   
   # Add files to SVN
   cd checkoutkeys
   svn add trunk/*
   svn add assets/*
   
   # Commit
   svn ci -m "Initial commit of CheckoutKeys License Manager 1.0.0"
   
   # Tag first release
   svn cp trunk tags/1.0.0
   svn ci -m "Tagging version 1.0.0"
   ```

## Current Status

### Completed
- Plugin code structure
- Admin dashboard
- Settings page
- API integration
- License sync functionality
- readme.txt

### Todo
1. Create banner images (772x250 and 1544x500)
2. Create icon images (256x256 and 128x128)
3. Take screenshots of dashboard, settings, and license list
4. Test in clean WordPress environment
5. Submit plugin for review

## Design Suggestions for Assets

### Banner
- Background: Gradient from blue (#2563eb) to darker blue
- Text: "CheckoutKeys" logo + tagline "License Key Management Made Simple"
- Icons: Key icon, Stripe logo, WordPress logo
- Modern, clean design

### Icon
- Simple key icon
- Blue gradient or solid blue (#2563eb)
- White key symbol
- Recognizable at small sizes

### Screenshots
1. Dashboard showing:
   - Plan usage card
   - License statistics
   - Recent licenses table
   
2. Settings page showing:
   - API key field
   - Sync button
   - Success message

3. License list showing:
   - Multiple license keys
   - Status indicators
   - Activation counts

## Resources

- Plugin Handbook: https://developer.wordpress.org/plugins/
- Plugin Directory: https://wordpress.org/plugins/
- SVN Documentation: https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/
- Assets Documentation: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/

## Contact

For questions about CheckoutKeys.com integration:
- Website: https://checkoutkeys.com
- Email: support@checkoutkeys.com
