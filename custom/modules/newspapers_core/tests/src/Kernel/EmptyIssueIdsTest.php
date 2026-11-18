<?php

namespace Drupal\Tests\newspapers_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests which empty issues cron is allowed to delete.
 *
 * Rows are inserted directly: saving issues and pages creates directories
 * under the site's real files directory and reindexes them in Solr.
 */
#[Group('newspapers_core')]
#[RunTestsInSeparateProcesses]
class EmptyIssueIdsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'digital_serial',
    'digital_serial_issue',
    'digital_serial_page',
    'digital_serial_title',
    'field',
    'file',
    'image',
    'newspapers_core',
    'node',
    'options',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('digital_serial_issue');
    $this->installEntitySchema('digital_serial_page');
  }

  /**
   * Inserts an issue row.
   */
  protected function insertIssue(int $id, int $created): void {
    $this->container->get('database')->insert('digital_serial_issue')->fields([
      'id' => $id,
      'uuid' => "issue-$id",
      'langcode' => 'en',
      'status' => 1,
      'created' => $created,
    ])->execute();
  }

  /**
   * Inserts a page row.
   */
  protected function insertPage(int $id, ?int $parent_issue): void {
    $this->container->get('database')->insert('digital_serial_page')->fields([
      'id' => $id,
      'uuid' => "page-$id",
      'langcode' => 'en',
      'page_no' => (string) $id,
      'parent_issue' => $parent_issue,
      'status' => 1,
    ])->execute();
  }

  /**
   * Tests that only old issues without pages are selected.
   */
  public function testSelectsOnlyOldEmptyIssues() {
    $cutoff = 1_000_000;
    // Old and empty: deleted.
    $this->insertIssue(1, $cutoff - 10);
    // Old with a page: kept.
    $this->insertIssue(2, $cutoff - 10);
    $this->insertPage(1, 2);
    // New and empty, as while ingest is still adding pages: kept.
    $this->insertIssue(3, $cutoff + 10);
    // A page without an issue must not stop the others from being found.
    $this->insertPage(2, NULL);

    $this->assertSame([1], _newspapers_core_empty_issue_ids($cutoff));
  }

}
