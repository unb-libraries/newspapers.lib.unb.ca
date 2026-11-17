<?php

namespace Drupal\Tests\digital_serial_issue\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the labels of digital serial titles and issues.
 *
 * The entities are not saved: saving creates storage directories under the
 * site's real files directory, and the labels only read field values.
 *
 * @group digital_serial_issue
 */
class EntityLabelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'digital_serial',
    'digital_serial_issue',
    'digital_serial_title',
    'field',
    'node',
    'options',
    'system',
    'text',
    'user',
  ];

  /**
   * The digital serial title storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $titleStorage;

  /**
   * The digital serial issue storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $issueStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    NodeType::create(['type' => 'publication', 'name' => 'Publication'])->save();
    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->titleStorage = $entity_type_manager->getStorage('digital_serial_title');
    $this->issueStorage = $entity_type_manager->getStorage('digital_serial_issue');
  }

  /**
   * Creates a digital title whose publication has the given name.
   */
  protected function createTitle(?string $publication_name) {
    $values = [];
    if ($publication_name !== NULL) {
      $values['parent_title'] = Node::create([
        'type' => 'publication',
        'title' => $publication_name,
      ]);
    }
    return $this->titleStorage->create($values);
  }

  /**
   * Tests the title label.
   */
  public function testTitleLabel() {
    $title = $this->createTitle('The News (Saint John, New Brunswick: 1906)');
    $this->assertSame('The News (Saint John, New Brunswick: 1906): Digital Issues', $title->label());
    $this->assertSame($title->label(), digital_serial_title_format_label($title));

    // The label is plain text, so markup characters are not escaped.
    $this->assertSame('Smith & Sons <Weekly>: Digital Issues', $this->createTitle('Smith & Sons <Weekly>')->label());

    // A title without a publication still has a label.
    $this->assertSame('Digital Issues', $this->createTitle(NULL)->label());
  }

  /**
   * Tests the issue label.
   */
  public function testIssueLabel() {
    $title = $this->createTitle('The News');

    $issue = $this->issueStorage->create([
      'parent_title' => $title,
      'issue_title' => 'The Daily News',
      'issue_date' => '1907-08-16',
      'issue_vol' => '12',
      'issue_issue' => '190',
    ]);
    $this->assertSame('The Daily News, 1907-08-16', $issue->label());

    // NULL volume and issue numbers do not affect the label.
    $issue = $this->issueStorage->create([
      'parent_title' => $title,
      'issue_title' => 'The News',
      'issue_date' => '1907-08-16',
      'issue_vol' => NULL,
      'issue_issue' => NULL,
    ]);
    $this->assertSame('The News, 1907-08-16', $issue->label());

    // An empty printed title falls back to the publication's title.
    $issue = $this->issueStorage->create([
      'parent_title' => $title,
      'issue_title' => '',
      'issue_date' => '1907-08-16',
    ]);
    $this->assertSame('The News, 1907-08-16', $issue->label());

    // Missing values are left out instead of raising errors.
    $issue = $this->issueStorage->create([
      'parent_title' => $title,
      'issue_title' => 'The News',
    ]);
    $this->assertSame('The News', $issue->label());
    $issue = $this->issueStorage->create(['issue_date' => '1907-08-16']);
    $this->assertSame('1907-08-16', $issue->label());
    $this->assertSame('Serial issue', $this->issueStorage->create([])->label());
  }

}
