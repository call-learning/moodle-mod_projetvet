# Customizing the Activity Form

This guide explains how to modify the activity form fields.

## Understanding the CSV Format

The form is defined in `data/default_activity_form.csv`:

```csv
"category";"idnumber";"name";"sortorder";"type";"description";"configdata"
```

### Column Definitions

- **category**: The category name (creates/groups fields together)
- **idnumber**: Unique identifier for the field (used in code)
- **name**: Display name shown to users
- **sortorder**: Order within category (1, 2, 3...)
- **type**: Field type (see below)
- **description**: Help text (currently unused)
- **configdata**: JSON configuration for the field

### Supported Field Types

#### 1. Text Field
```csv
"Activity Details";"activity_title";"Activity title";1;"text";"";"{}"
```

#### 2. Textarea Field
```csv
"Activity Details";"summary";"Summary";2;"textarea";"";"{\"rows\": 4}"
```
Config options:
- `rows`: Number of rows (default: 4)

#### 3. Number Field
```csv
"Activity Details";"hours";"Hours";7;"number";"";"{}"
"Activity Details";"credits";"Credits";8;"number";"";"{\"max\": 5}"
```
Config options:
- `max`: Maximum value
- `min`: Minimum value

#### 4. Select Dropdown
```csv
"Activity Details";"year";"Year";3;"select";"";"{\"options\":{\"1\":\"Year 1\",\"2\":\"Year 2\",\"3\":\"Year 3\",\"4\":\"Year 4\",\"5\":\"Year 5\"}}"
```
Config format:
```json
{
  "options": {
    "1": "Option Label 1",
    "2": "Option Label 2",
    "3": "Option Label 3"
  }
}
```

#### 5. Checkbox
```csv
"Activity Details";"completed";"Completed";6;"checkbox";"";"{}"
```

## Adding a New Field

### Example: Add a "Supervisor" Text Field

1. Edit `data/default_activity_form.csv`
2. Add a new line:
```csv
"Activity Details";"supervisor";"Supervisor name";9;"text";"";"{}"
```

3. Reimport the fields:
```php
require_once('config.php');
require_once($CFG->dirroot . '/mod/projetvet/classes/setup.php');
\mod_projetvet\setup::create_default_activities();
```

4. Clear caches:
```bash
php admin/cli/purge_caches.php
```

The field will automatically appear in the form!

## Adding a New Category

### Example: Add "Project Information" Category

```csv
"Project Information";"project_start";"Start Date";1;"text";"";"{}"
"Project Information";"project_end";"End Date";2;"text";"";"{}"
"Project Information";"project_budget";"Budget";3;"number";"";"{}"
```

Categories are automatically created when fields reference them.

## Advanced: Multi-Select (Future Enhancement)

To add multi-select support, you would need to:

1. Add 'multiselect' to `act_field::FIELD_TYPES`
2. Update `act_field::display_value()` to handle arrays
3. Update `act_field::convert_to_raw_value()` to serialize arrays
4. Update the form in `activity_entry_form.php` to use `select` element with `multiple` attribute
5. Store as JSON in `textvalue` field

## Accessing Field Values in Code

```php
use mod_projetvet\local\api\activities;

// Get an entry
$entry = activities::get_entry($entryid);

// Access field values by iterating categories
foreach ($entry->categories as $category) {
    foreach ($category->fields as $field) {
        if ($field->idnumber === 'activity_title') {
            echo $field->value; // Raw value
            echo $field->displayvalue; // Formatted value
        }
    }
}

// Or use the helper (if added to the activities class)
$title = activities::get_field_value($entry, 'activity_title');
```

## Display in Table

To show a new field in the activity list table:

1. Edit `renderer.php`
2. Modify the `render_activity_list()` method
3. Add a new column header:
```php
$table->head = [
    get_string('activitytitle', 'mod_projetvet'),
    get_string('supervisor', 'mod_projetvet'), // New column
    get_string('year', 'mod_projetvet'),
    // ...
];
```

4. Add the data:
```php
$row[] = self::get_activity_field_value($activity, 'supervisor');
```

5. Add language string to `lang/en/projetvet.php`:
```php
$string['supervisor'] = 'Supervisor';
```

## Field Validation

To add validation:

1. Edit `classes/form/activity_entry_form.php`
2. Add rules in the `definition()` method:
```php
case 'text':
    $mform->addElement('text', $fieldname, $field->name);
    $mform->setType($fieldname, PARAM_TEXT);
    $mform->addRule($fieldname, null, 'required', null, 'client'); // Make required
    break;
```

Or for custom validation, override `validation()`:
```php
public function validation($data, $files) {
    $errors = parent::validation($data, $files);

    if (!empty($data['field_123']) && strlen($data['field_123']) < 5) {
        $errors['field_123'] = 'Must be at least 5 characters';
    }

    return $errors;
}
```

## Tips

1. **Always use unique idnumbers** - These are used as keys
2. **Set sortorder sequentially** - Determines display order
3. **Test JSON configdata** - Invalid JSON will cause errors
4. **Clear caches after changes** - Especially for structure changes
5. **Backup before reimporting** - Fields with same idnumber will be updated

## Common ConfigData Examples

### Text with placeholder
```json
{"placeholder": "Enter value here"}
```

### Number with range
```json
{"min": 0, "max": 100, "step": 1}
```

### Textarea with custom size
```json
{"rows": 10, "cols": 80}
```

### Select with many options
```json
{
  "options": {
    "1": "Option A",
    "2": "Option B",
    "3": "Option C",
    "4": "Option D"
  }
}
```
