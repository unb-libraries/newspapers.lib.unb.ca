<?php

/**
 * @file
 * Post update functions for digital_serial_issue.
 */

/**
 * Fixes four issues whose fields hold PHP importer artefacts.
 *
 * Curly quotes in their metadata.php made the old importer store the
 * placeholder "ISSUE_TITLE" as the title of two issues, and keep the quote
 * marks around the missing pages of two others. Each issue is changed only if
 * its UUID and current value are still the ones found on production.
 */
function digital_serial_issue_post_update_fix_importer_artefacts() {
  $fixes = [
    // The Christian Visitor, 1857-09-02 and 1858-12-08.
    [8051, '781919cd-a0c4-43fc-8103-84c8ee04af44', 'issue_title', 'ISSUE_TITLE', 'The Christian Visitor'],
    [8055, '276d709f-af6a-4eb2-ae31-e717d00a4d32', 'issue_title', 'ISSUE_TITLE', 'The Christian Visitor'],
    // The Daily Mail, 1913-11-06.
    [1119, 'ae771dbc-11dc-4578-8547-1599444dad52', 'issue_missingp', '‘1’', '1'],
    // The Christian Visitor, 1881-07-06.
    [8730, '83aeb79f-3fed-4980-a512-f05684a288fa', 'issue_missingp', '‘8’', '8'],
  ];
  $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_issue');
  $messages = [];
  foreach ($fixes as [$id, $uuid, $field, $old, $new]) {
    $issue = $storage->load($id);
    if (!$issue || $issue->uuid() !== $uuid || $issue->get($field)->value !== $old) {
      $messages[] = "issue $id: skipped, not the expected issue or value";
      continue;
    }
    $issue->set($field, $new)->save();
    $messages[] = "issue $id: $field '$old' -> '$new'";
  }
  return implode('; ', $messages);
}
