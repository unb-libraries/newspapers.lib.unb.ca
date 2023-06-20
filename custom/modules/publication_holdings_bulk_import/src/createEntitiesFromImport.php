<?php

/**
 * @file
 * Creates the entities needed for a publication holdings import.
 *
 * This is intended to be used with an empty content set, and should only ever
 * be used in local.
 */

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

$set_nodes = [];
$set_tids = [];
$handle = fopen("/app/html/modules/custom/newspapers_core/broken.csv", "r");

$header_line = fgets($handle);

$holding_types = [
  'Print' => 'Print',
  'Online' => 'Online',
  'Microform' => 'Microform',
  'Digital' => 'Online',
];

foreach ($holding_types as $type) {
  $result = Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'serial_holding_types')
    ->condition('name', $type)
    ->execute();
  if (!empty($result)) {
    // Create a new term.
    $term = Term::create([
      'vid' => 'serial_holding_types',
      'name' => $type,
    ]);
    $term->save();
  }
}

while (($row = fgetcsv($handle)) !== FALSE) {
  $institution_tid = $row[1];
  $publication_nid = $row[2];

  if (!in_array($publication_nid, $set_nodes)) {
    $set_nodes[] = $publication_nid;
    $result = Drupal::entityQuery('node')
      ->condition('type', 'publication')
      ->condition('nid', $publication_nid)
      ->execute();
    if (empty($result)) {
      echo "Creating node $publication_nid...\n";
      $title = 'Publication ' . $publication_nid;
      $node = Node::create([
        'type' => 'publication',
        'title' => $title,
      ]);
      $node->set('nid', $publication_nid);
      $node->save();
    }
    else {
      echo "Node $publication_nid already exists in database.\n";
    }
  }
  else {
    echo "Node $publication_nid was already created.\n";
  }


  if (!in_array($institution_tid, $set_tids)) {
    $set_tids[] = $institution_tid;
    // Entity query the db to see if institution term w/ institution_tid exists.
    $result = Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'institution')
      ->condition('tid', $institution_tid)
      ->execute();
    if (empty($result)) {
      echo "Creating term $institution_tid...\n";
      $name = 'Institution ' . $institution_tid;
      $term = Term::create([
        'vid' => 'institution',
        'name' => $name,
        'tid' => $institution_tid,
      ]);
      $term->set('tid', $institution_tid);
      $term->save();
    }
    else {
      echo "Term $institution_tid already exists in database.\n";
    }
  }
  else {
    echo "Term $institution_tid was already created.\n";
  }

}
