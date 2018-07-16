<?php

namespace Drupal\visreg_content\Event;

use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\node\Entity\Node;

/**
 * Defines the migrate event subscriber.
 */
class MicroformsTitleFamilyMigrateEvent implements EventSubscriberInterface {

  const MIGRATION_ID = '1_visreg_content_microforms_families';
  const FAMILY_TAXONOMY_ID = 'family';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PREPARE_ROW][] = ['onPrepareRow', 0];
    return $events;
  }

  /**
   * React to a new row.
   *
   * @param \Drupal\migrate_plus\Event\MigratePrepareRowEvent $event
   *   The prepare-row event.
   */
  public function onPrepareRow(MigratePrepareRowEvent $event) {
    $row = $event->getRow();
    $migration = $event->getMigration();
    $migration_id = $migration->id();

    // Only act on rows for this migration.
    if ($migration_id == self::MIGRATION_ID) {

      // First, ensure the Term exists.
      $group_nid = $row->getSourceProperty('parent_family');

      $query = \Drupal::entityQuery('node')
        ->condition('type', 'publication')
        ->condition('status', 1)
        ->condition('field_previous_identifications', $group_nid);
      $nids = $query->execute();

      foreach ($nids as $nid) {
        $group_node = Node::Load($nid);
        $group_title = $group_node->getTitle();

        $group_tid = $this->taxTermExists($group_title, 'name', $this::FAMILY_TAXONOMY_ID);
        if (!empty($group_tid)) {
          $term = Term::load($group_tid);
        }
        else {
          $term = Term::create([
            'vid' => $this::FAMILY_TAXONOMY_ID,
            'name' => $group_node->getTitle(),
          ]);
          $term->save();
        }
      }

      // Load the publication this row corresponds to and attach the term.
      $cur_title = $row->getSourceProperty('title');
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'publication')
        ->condition('title', $cur_title);
      $nids = $query->execute();

      foreach ($nids as $nid) {
        $row_node = Node::Load($nid);
        $row_node->set('field_family', $term->id());
        $row_node->set('field_this_is_part_of_a_family', TRUE);
        $row_node->save();
      }
    }
  }

  /**
   * Check if a taxonomy term exists.
   *
   * @param string $value
   *   The name of the term.
   * @param string $field
   *   The field to match when validating.
   * @param string $vocabulary
   *   The vid to match.
   *
   * @return mixed
   *   Contains an INT of the tid if exists, FALSE otherwise.
   */
  public function taxTermExists($value, $field, $vocabulary) {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $vocabulary);
    $query->condition($field, $value);

    $tids = $query->execute();
    if (!empty($tids)) {
      foreach ($tids as $tid) {
        return $tid;
      }
    }
    return FALSE;
  }

}
