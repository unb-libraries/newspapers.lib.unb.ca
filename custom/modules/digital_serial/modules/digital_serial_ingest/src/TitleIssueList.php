<?php

namespace Drupal\digital_serial_ingest;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;

/**
 * Lists a title's issues with their page counts, for the ingest tool.
 *
 * The tool matches issues imported before it existed by their metadata, so it
 * needs every issue of a title at once. Titles have thousands of issues, so
 * this reads the tables directly instead of loading entities.
 */
final class TitleIssueList {

  /**
   * Constructs a TitleIssueList.
   */
  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Lists the issues of a title, oldest first.
   *
   * @return array<int, array<string, mixed>>
   *   One summary per issue: id, uuid, issue_title, volume, issue, edition,
   *   date and page_count.
   */
  public function forTitle(int $title_id): array {
    $column = fn(string $field, string $property = 'value') => $this->column('digital_serial_issue', $field, $property);

    $query = $this->database->select($this->baseTable('digital_serial_issue'), 'i');
    $query->addField('i', 'id');
    $query->addField('i', 'uuid');
    foreach ([
      'issue_title' => 'issue_title',
      'volume' => 'issue_vol',
      'issue' => 'issue_issue',
      'edition' => 'issue_edition',
      'date' => 'issue_date',
    ] as $alias => $field) {
      $query->addField('i', $column($field), $alias);
    }
    $query->condition('i.' . $column('parent_title', 'target_id'), $title_id);
    $query->orderBy('date')->orderBy('i.id');

    $counts = $this->pageCounts($title_id, $column('parent_title', 'target_id'));
    $list = [];
    foreach ($query->execute() as $row) {
      $list[] = [
        'id' => (int) $row->id,
        'uuid' => $row->uuid,
        'issue_title' => (string) $row->issue_title,
        'volume' => (string) $row->volume,
        'issue' => (string) $row->issue,
        'edition' => (string) $row->edition,
        'date' => $row->date,
        'page_count' => $counts[(int) $row->id] ?? 0,
      ];
    }
    return $list;
  }

  /**
   * Counts every page, published or not, of each issue of a title.
   *
   * @return array<int, int>
   *   Page counts keyed by issue ID; issues without pages are absent.
   */
  protected function pageCounts(int $title_id, string $title_column): array {
    $parent_column = $this->column('digital_serial_page', 'parent_issue', 'target_id');

    $query = $this->database->select($this->baseTable('digital_serial_page'), 'p');
    $query->innerJoin($this->baseTable('digital_serial_issue'), 'i', "i.id = p.$parent_column");
    $query->addField('p', $parent_column, 'issue_id');
    $query->addExpression('COUNT(p.id)', 'page_count');
    $query->condition("i.$title_column", $title_id);
    $query->groupBy("p.$parent_column");
    return array_map('intval', $query->execute()->fetchAllKeyed());
  }

  /**
   * Gets the base table of an entity type.
   */
  protected function baseTable(string $entity_type): string {
    return $this->entityTypeManager->getDefinition($entity_type)->getBaseTable();
  }

  /**
   * Gets the column holding a base field property.
   */
  protected function column(string $entity_type, string $field, string $property): string {
    $storage = $this->entityTypeManager->getStorage($entity_type);
    assert($storage instanceof SqlEntityStorageInterface);
    $definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type);
    return $storage->getTableMapping()->getFieldColumnName($definitions[$field], $property);
  }

}
