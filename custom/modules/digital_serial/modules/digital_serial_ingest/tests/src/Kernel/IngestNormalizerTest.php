<?php

namespace Drupal\Tests\digital_serial_ingest\Kernel;

use Drupal\digital_serial_ingest\IngestNormalizer;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the documents the ingest endpoints return.
 *
 * Issues and pages are not saved: saving creates directories under the site's
 * real files directory and reindexes issues. The documents only read values.
 */
#[Group('digital_serial_ingest')]
#[RunTestsInSeparateProcesses]
class IngestNormalizerTest extends KernelTestBase {

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
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Creates an unsaved page with a stored image file.
   */
  protected function createPage(int $id, string $page_no, string $page_sort, string $filename) {
    file_put_contents("public://$filename", $filename);
    $file = File::create(['uri' => "public://serials/pages/48/7/$filename", 'filename' => $filename]);
    $file->save();
    return $this->container->get('entity_type.manager')->getStorage('digital_serial_page')->create([
      'id' => $id,
      'uuid' => sprintf('00000000-0000-4000-8000-%012d', $id),
      'page_no' => $page_no,
      'page_sort' => $page_sort,
      'page_image' => ['target_id' => $file->id()],
      'image_sha256' => str_repeat((string) $id, 64),
    ]);
  }

  /**
   * Tests the issue document, including page order and asset flags.
   */
  public function testIssue() {
    $issue = $this->container->get('entity_type.manager')->getStorage('digital_serial_issue')->create([
      'id' => 7,
      'uuid' => '6f1c2a4e-1b7d-4c3e-9a51-2d3f4b5c6d7e',
      'parent_title' => ['target_id' => 48],
      'issue_title' => 'Supplement to The Saint John Gazette',
      'issue_vol' => '18',
      'issue_issue' => '25',
      'issue_date' => '1803-07-04',
      'issue_language' => 'eng',
      'issue_media' => 'print',
    ]);
    $pages = [
      $this->createPage(3, '2', '0002', '7-0002.jpg'),
      $this->createPage(1, '1', '0001', '7-0001.jpg'),
      $this->createPage(2, '1_0', '0001', '7-0001_0.jpg'),
    ];
    $normalizer = new IngestNormalizer(fn($page) => ['dzi' => $page->id() == 1, 'pdf' => $page->id() != 3]);

    $document = $normalizer->issue($issue, $pages);

    $this->assertSame(7, $document['id']);
    $this->assertSame('6f1c2a4e-1b7d-4c3e-9a51-2d3f4b5c6d7e', $document['uuid']);
    $this->assertSame(48, $document['title_id']);
    $this->assertSame('Supplement to The Saint John Gazette', $document['issue_title']);
    $this->assertSame('18', $document['volume']);
    $this->assertSame('25', $document['issue']);
    $this->assertSame('', $document['edition']);
    $this->assertSame('1803-07-04', $document['date']);
    $this->assertSame('', $document['missing_pages']);
    $this->assertSame('eng', $document['language']);

    // Sorted by page_sort, then page_no, then ID.
    $this->assertSame([1, 2, 3], array_column($document['pages'], 'id'));
    $this->assertSame([
      'id' => 1,
      'uuid' => '00000000-0000-4000-8000-000000000001',
      'page_no' => '1',
      'page_sort' => '0001',
      'image_path' => 'serials/pages/48/7/7-0001.jpg',
      'asset_basename' => '7-0001',
      'image_sha256' => str_repeat('1', 64),
      'has_dzi' => TRUE,
      'has_pdf' => TRUE,
    ], $document['pages'][0]);
    $this->assertSame('7-0001_0', $document['pages'][1]['asset_basename']);
    $this->assertFalse($document['pages'][2]['has_pdf']);
  }

  /**
   * Tests that pages with non-.jpg images never report assets.
   *
   * The page's own asset lookups replace '.jpg' in the name, so for any other
   * name they would find the image itself.
   */
  public function testViewerAssetStateIgnoresOtherImageNames() {
    $page = $this->createPage(1, '1', '0001', '7-0001.tif');
    $this->assertSame(['dzi' => FALSE, 'pdf' => FALSE], IngestNormalizer::viewerAssetState($page));
  }

  /**
   * Tests that an issue without a title has a null title ID.
   */
  public function testIssueWithoutTitle() {
    $issue = $this->container->get('entity_type.manager')->getStorage('digital_serial_issue')->create([
      'id' => 8,
      'issue_date' => '1909-01-29',
    ]);
    $document = (new IngestNormalizer(fn() => ['dzi' => FALSE, 'pdf' => FALSE]))->issue($issue, []);
    $this->assertNull($document['title_id']);
    $this->assertSame([], $document['pages']);
  }

  /**
   * Tests the title document.
   */
  public function testTitle() {
    NodeType::create(['type' => 'publication', 'name' => 'Publication'])->save();
    $title = $this->container->get('entity_type.manager')->getStorage('digital_serial_title')->create([
      'id' => 48,
      'parent_title' => Node::create(['type' => 'publication', 'title' => 'The News']),
    ]);
    $document = (new IngestNormalizer())->title($title);
    $this->assertSame(48, $document['id']);
    $this->assertSame('The News', $document['name']);
    $this->assertSame('The News: Digital Issues', $document['label']);
  }

}
