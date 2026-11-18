<?php

namespace Drupal\Tests\digital_serial_ingest\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests listing a title's issues for the ingest tool.
 *
 * Rows are inserted directly: saving issues and pages creates directories
 * under the site's real files directory and reindexes them.
 */
#[Group('digital_serial_ingest')]
#[RunTestsInSeparateProcesses]
class TitleIssueListTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'digital_serial',
    'digital_serial_ingest',
    'digital_serial_issue',
    'digital_serial_page',
    'digital_serial_title',
    'field',
    'file',
    'image',
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
  protected function insertIssue(int $id, int $title_id, string $date, string $volume = '', string $issue_title = 'The News'): void {
    $this->container->get('database')->insert('digital_serial_issue')->fields([
      'id' => $id,
      'uuid' => "issue-$id",
      'langcode' => 'en',
      'status' => 1,
      'parent_title' => $title_id,
      'issue_title' => $issue_title,
      'issue_vol' => $volume,
      'issue_issue' => (string) $id,
      'issue_date' => $date,
    ])->execute();
  }

  /**
   * Inserts a page row.
   */
  protected function insertPage(int $id, int $parent_issue, int $status = 1): void {
    $this->container->get('database')->insert('digital_serial_page')->fields([
      'id' => $id,
      'uuid' => "page-$id",
      'langcode' => 'en',
      'page_no' => (string) $id,
      'parent_issue' => $parent_issue,
      'status' => $status,
    ])->execute();
  }

  /**
   * Tests that a title's issues are listed oldest first with page counts.
   */
  public function testListsIssuesWithPageCounts() {
    $this->insertIssue(3, 48, '1909-02-05', '33');
    $this->insertIssue(1, 48, '1909-01-29', '33', 'Supplement to The News');
    $this->insertIssue(2, 48, '1909-01-29', '33');
    $this->insertIssue(4, 12, '1803-07-04');
    $this->insertPage(1, 1);
    $this->insertPage(2, 1, 0);
    $this->insertPage(3, 3);
    $this->insertPage(4, 4);

    $list = $this->container->get('digital_serial_ingest.title_issue_list')->forTitle(48);

    $this->assertSame([1, 2, 3], array_column($list, 'id'));
    // Unpublished pages count: the tool must not upload them again.
    $this->assertSame([2, 0, 1], array_column($list, 'page_count'));
    $this->assertSame([
      'id' => 1,
      'uuid' => 'issue-1',
      'issue_title' => 'Supplement to The News',
      'volume' => '33',
      'issue' => '1',
      'edition' => '',
      'date' => '1909-01-29',
      'page_count' => 2,
    ], $list[0]);
    $this->assertSame([], $this->container->get('digital_serial_ingest.title_issue_list')->forTitle(99));
  }

}
