# Installation and Testing Guide

## Installation Steps

1. **Upgrade the plugin**
   ```bash
   cd /Users/basbrands/moodles/projetvet/moodle
   php admin/cli/upgrade.php
   ```
   Or visit: Site administration > Notifications

2. **Verify tables were created**
   Check that these tables exist in your database:
   - `mdl_projetvet_act_cat`
   - `mdl_projetvet_act_field`
   - `mdl_projetvet_act_entry`
   - `mdl_projetvet_act_data`

3. **Verify default fields were imported**
   Check the `mdl_projetvet_act_cat` and `mdl_projetvet_act_field` tables for data.

4. **Clear caches**
   ```bash
   php admin/cli/purge_caches.php
   ```

## Testing

1. **Create a Projetvet activity**
   - Go to a course
   - Turn editing on
   - Add activity > Projetvet
   - Configure and save

2. **Test as a student**
   - View the Projetvet activity
   - Click "New Activity" button
   - Fill in the form
   - Save and verify it appears in the list

3. **Test editing**
   - Click "Edit" button on an activity
   - Modify values
   - Save and verify changes

4. **Test as teacher**
   - View the activity as a teacher
   - Verify you can edit student activities

## Known Issues to Fix

The following items show lint errors but are expected in a Moodle environment:

1. **Language strings**: Some strings show as "not found" but will work once Moodle's language cache is built
2. **Type hints**: Classes like `moodle_exception`, `context_module`, `html_writer` are core Moodle classes available at runtime
3. **Database tables**: Tables don't exist until installation runs

## Troubleshooting

### Forms don't appear
- Check JavaScript console for errors
- Verify AMD module was compiled: `php admin/cli/purge_caches.php`
- Check that the JS file exists: `mod/projetvet/amd/build/activity_entry_form.min.js`

### No default fields
- Run the importer manually:
  ```php
  require_once('config.php');
  \mod_projetvet\setup::create_default_activities();
  ```

### Cache issues
- Purge all caches: Site administration > Development > Purge all caches
- Or: `php admin/cli/purge_caches.php`

## Next Steps

1. **Compile AMD JavaScript**
   ```bash
   cd /Users/basbrands/moodles/projetvet/moodle
   npm install
   grunt amd --force
   ```
   Or use: `grunt watch` for development

2. **Add web service for delete**
   Create `classes/external/delete_activity.php` for AJAX delete

3. **Enhance the UI**
   - Add Bootstrap icons
   - Improve table styling
   - Add pagination for large lists
   - Add search/filter functionality

4. **Add "Send to teacher" feature**
   - Add status field to act_entry
   - Add teacher notification
   - Add teacher review interface

5. **Create unit tests**
   - Test persistent classes
   - Test API methods
   - Test form validation

## File Structure Created

```
mod/projetvet/
├── classes/
│   ├── local/
│   │   ├── api/
│   │   │   └── activities.php          # Main API
│   │   ├── importer/
│   │   │   └── fields_importer.php     # CSV importer
│   │   └── persistent/
│   │       ├── act_cat.php             # Category entity
│   │       ├── act_data.php            # Data entity
│   │       ├── act_entry.php           # Entry entity
│   │       └── act_field.php           # Field entity
│   ├── form/
│   │   └── activity_entry_form.php     # Modal form
│   └── setup.php                        # Setup routines
├── amd/src/
│   └── activity_entry_form.js           # JavaScript for modals
├── data/
│   └── default_activity_form.csv        # Default fields
├── db/
│   ├── access.php                       # Capabilities (updated)
│   ├── caches.php                       # Cache definitions
│   ├── install.php                      # Post-install script
│   └── install.xml                      # Database schema
├── lang/en/
│   └── projetvet.php                    # Language strings (updated)
├── renderer.php                         # Output renderer
├── view.php                             # Main view page (updated)
└── README_ACTIVITIES.md                 # Documentation
```
