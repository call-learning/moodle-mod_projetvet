<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

echo "=== Categories ===\n";
$cats = $DB->get_records('projetvet_act_cat', [], 'sortorder');
foreach ($cats as $cat) {
    echo sprintf("%d: %s (idnumber: %s)\n", $cat->sortorder, $cat->name, $cat->idnumber);
}

echo "\n=== Fields by Category ===\n";
$fields = $DB->get_records('projetvet_act_field', [], 'sortorder');
$currentcat = null;
foreach ($fields as $field) {
    if ($field->categoryid != $currentcat) {
        $cat = $DB->get_record('projetvet_act_cat', ['id' => $field->categoryid]);
        echo "\n--- {$cat->name} ---\n";
        $currentcat = $field->categoryid;
    }
    echo sprintf("  %d: %s (%s) - type: %s, capability: %s, entrystatus: %d\n",
        $field->sortorder,
        $field->name,
        $field->idnumber,
        $field->type,
        $field->capability ?: 'none',
        $field->entrystatus
    );
}
