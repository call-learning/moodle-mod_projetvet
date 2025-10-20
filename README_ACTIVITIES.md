# Projetvet - Activity Workflow Module

A Moodle activity plugin for managing student project activities through a dynamic form-based workflow.

## Overview

This plugin allows students to create, view, edit, and manage their project activities. The form structure is defined via CSV files and stored in the database, making it highly customizable.

## Structure

The plugin follows the architecture pattern from mod_competvet for case management, adapted for general project activities.

### Database Tables

- **projetvet_act_cat**: Activity field categories
- **projetvet_act_field**: Activity field definitions
- **projetvet_act_entry**: Activity entries (one per student activity)
- **projetvet_act_data**: Activity data values

### Key Classes

#### Persistent Classes (`classes/local/persistent/`)
- `act_cat`: Category entity
- `act_field`: Field definition entity (supports text, textarea, number, select, checkbox)
- `act_entry`: Activity entry entity
- `act_data`: Activity data entity with dynamic value storage

#### API Classes (`classes/local/api/`)
- `activities`: Main API for CRUD operations on activities
  - `get_activity_structure()`: Get the form structure
  - `create_activity()`: Create a new activity entry
  - `update_activity()`: Update an existing activity
  - `delete_activity()`: Delete an activity
  - `get_entry()`: Get a single entry with all data
  - `get_activity_list()`: Get list of activities for display

#### Form Classes (`classes/form/`)
- `activity_entry_form`: Dynamic modal form for creating/editing activities

#### Importer Classes (`classes/local/importer/`)
- `fields_importer`: Import field definitions from CSV

### Default Activity Fields

The plugin comes with pre-configured fields defined in `data/default_activity_form.csv`:

- **Activity title** (text)
- **Summary** (textarea)
- **Year** (select: Year 1-5)
- **Category** (select: Cat 1-3)
- **Rang** (select: A, B, C)
- **Completed** (checkbox)
- **Hours** (number)
- **Credits** (number, max 5)

### Frontend

- **JavaScript**: `amd/src/activity_entry_form.js` handles modal form display and submission
- **Renderer**: `renderer.php` renders the activity list table
- **View**: `view.php` displays the main activity list page

## Installation

1. Copy the plugin to `mod/projetvet/`
2. Visit Site administration > Notifications to install
3. The default activity fields will be automatically imported

## Usage

### For Students

1. Navigate to a Projetvet activity in a course
2. Click "New Activity" to create an activity entry
3. Fill in the form fields
4. Save the activity
5. View, edit, or delete activities from the list

### For Teachers

Teachers can:
- View all student activities
- Edit any student's activities
- Delete activities (capability: `mod/projetvet:edit`)

### Customizing Fields

To customize the form fields:

1. Edit `data/default_activity_form.csv`
2. Format: `"category";"idnumber";"name";"sortorder";"type";"description";"configdata"`
3. Run the importer via the setup class

Supported field types:
- `text`: Single-line text input
- `textarea`: Multi-line text input
- `number`: Numeric input
- `select`: Dropdown selection
- `checkbox`: Boolean checkbox

## Capabilities

- `mod/projetvet:view`: View the activity (students, teachers)
- `mod/projetvet:edit`: Edit any activity (teachers)
- `mod/projetvet:addinstance`: Add plugin to course (teachers)

## Future Enhancements

- "Send to teacher" functionality
- Web service for delete operation
- Activity filtering and sorting
- Export/import of student activities
- Reports and analytics
- Teacher feedback system

## Credits

Based on the case management structure from mod_competvet.

Copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
Licensed under the GNU GPL v3 or later.
