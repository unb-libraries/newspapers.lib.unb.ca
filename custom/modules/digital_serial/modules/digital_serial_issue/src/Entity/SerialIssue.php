<?php

namespace Drupal\digital_serial_issue\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\digital_serial_title\Entity\SerialTitleInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntity;
use Drupal\user\UserInterface;

/**
 * Defines the Serial issue entity.
 *
 * @ingroup digital_serial_issue
 *
 * @ContentEntityType(
 *   id = "digital_serial_issue",
 *   label = @Translation("Serial issue"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\digital_serial_issue\SerialIssueListBuilder",
 *     "views_data" = "Drupal\digital_serial_issue\Entity\SerialIssueViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\digital_serial_issue\Form\SerialIssueForm",
 *       "add" = "Drupal\digital_serial_issue\Form\SerialIssueForm",
 *       "edit" = "Drupal\digital_serial_issue\Form\SerialIssueForm",
 *       "delete" = "Drupal\digital_serial_issue\Form\SerialIssueDeleteForm",
 *     },
 *     "access" = "Drupal\digital_serial_issue\SerialIssueAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\digital_serial_issue\SerialIssueHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "digital_serial_issue",
 *   admin_permission = "administer serial issue entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/digital_serial/digital_serial_issue/{digital_serial_issue}",
 *     "add-form" = "/digital_serial/digital_serial_issue/add",
 *     "edit-form" = "/digital_serial/digital_serial_issue/{digital_serial_issue}/edit",
 *     "delete-form" = "/digital_serial/digital_serial_issue/{digital_serial_issue}/delete",
 *     "collection" = "/digital_serial/digital_serial_issue",
 *   },
 *   field_ui_base_route = "digital_serial_issue.settings"
 * )
 */
class SerialIssue extends ContentEntityBase implements SerialIssueInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIssueTitle() {
    return $this->get('issue_title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setIssueTitle($issue_title) {
    $this->set('issue_title', $issue_title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIssueVol() {
    return $this->get('issue_vol')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setIssueVol($issue_vol) {
    $this->set('issue_vol', $issue_vol);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setIssueVolSort($issue_vol_sort) {
    $this->set('issue_vol_sort', $issue_vol_sort);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIssueIssue() {
    return $this->get('issue_issue')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setIssueIssue($issue_issue) {
    $this->set('issue_issue', $issue_issue);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setIssueIssueSort($issue_issue_sort) {
    $this->set('issue_issue_sort', $issue_issue_sort);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIssueEdition() {
    return $this->get('issue_edition')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setIssueEdition($issue_edition) {
    $this->set('issue_edition', $issue_edition);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentTitle() {
    return $this->get('parent_title')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setParentTitle(SerialTitleInterface $title) {
    $this->set('parent_title', $title->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setParentTitleById($title_id) {
    $this->set('parent_title', $title_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayTitle() {
    $date = $this->get('issue_date')->date;
    $pub_date = $date->format('Y-m-d');
    return $pub_date;
  }

  /**
   * {@inheritdoc}
   */
  public function getYear() {
    return (int) $this->get('issue_date')->date->format('Y');
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['issue_title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Printed Title'))
      ->setDescription(t('Printed title.'))
      ->setSettings([
        'max_length' => 256,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -50,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -50,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['issue_vol'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Volume'))
      ->setDescription(t('Volume number of the issue.'))
      ->setSettings([
        'max_length' => 32,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -45,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -45,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['issue_vol_sort'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Volume Sort'))
      ->setDescription(t('Sort volume number of the issue.'))
      ->setSettings([
        'max_length' => 32,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['issue_issue'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Issue'))
      ->setDescription(t('Issue number of the issue.'))
      ->setSettings([
        'max_length' => 32,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -40,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -40,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['issue_issue_sort'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Issue Sort'))
      ->setDescription(t('Sort issue number of the issue.'))
      ->setSettings([
        'max_length' => 32,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['issue_edition'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Edition'))
      ->setDescription(t('Edition of the issue.'))
      ->setSettings([
        'max_length' => 128,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -35,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -35,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['issue_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Publication Date'))
      ->setDescription(t('The date the issue was published.'))
      ->setRevisionable(FALSE)
      ->setSettings([
        'datetime_type' => 'date',
      ])
      ->setDefaultValue('')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'settings' => [
          'format_type' => 'html_date',
        ],
        'weight' => 14,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime',
        'weight' => -48,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['issue_missingp'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Missing Pages'))
      ->setDescription(t('Pages missing from the issue.'))
      ->setSettings([
        'max_length' => 128,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -25,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -25,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['issue_errata'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Errata'))
      ->setDescription(t('Add issue errata.'))
      ->setSettings([
        'default_value' => '',
        'text_processing' => 0,
      ])
      ->setDisplayOptions('form', [
        'settings' => [
          'rows' => 3,
        ],
        'type' => 'text_textarea',
        'weight' => -20,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => -20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['issue_language'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Language'))
      ->setDescription(t('Select the language of the issue.'))
      ->setSettings([
        'allowed_values' => [
          'eng' => 'English',
          'fre' => 'French',
        ],
      ])
      ->setDefaultValue('eng')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => -15,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['issue_media'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source Media'))
      ->setDescription(t('Enter Source Media, eg. <i>Print</i>.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('Print')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['parent_title'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Parent Title'))
      ->setDescription(t('Parent Title this issue belongs to'))
      ->setSettings(
        [
          'target_type' => 'digital_serial_title',
          'handler' => 'default',
        ]
      );

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Serial issue is published.'))
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    $this->deleteChildPages();
    $parent_title = $this->getParentTitle();
    parent::delete();
    $parent_title->updateDigitalHoldingRecord();
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    $parent_title = $this->getParentTitle();

    if (empty($this->getIssueTitle())) {
      $this->setIssueTitle($parent_title->getParentPublication()->getTitle());
    }

    parent::save();
    $parent_title->updateDigitalHoldingRecord();
  }

  /**
   * Delete the child pages that belong to this issue.
   */
  private function deleteChildPages() {
    $page_ids = $this->getChildPageIds();
    foreach ($page_ids as $page_id) {
      $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_page');
      $page = $storage->load($page_id);
      $page->delete();
    }
  }

  /**
   * Reindexes this issue in solr.
   */
  public function reIndexInSolr() {
    $page_ids = $this->getChildPageIds();
    if (!empty($page_ids)) {
      $page_list = array_values($page_ids);
      $first_entity_id = array_shift($page_list);
      $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_page');
      $first_page_entity = $storage->load($first_entity_id);
      $index_list = ContentEntity::getIndexesForEntity($first_page_entity);
      $language_page_ids = array_map(
        function ($val) {
          return $val . ':en';
        },
      $page_ids);
      $page_entities = $storage->loadMultiple($page_list);
      if (!empty($page_entities) && !empty($index_list)) {
        foreach ($index_list as $index) {
          $index->trackItemsUpdated("entity:digital_serial_page", $language_page_ids);
        }
      }
    }
  }

  /**
   * Gets this issue's child page entity IDs.
   *
   * @return int[]
   *   An array of the issue'sa child pages.
   */
  public function getChildPageIds() {
    $query = \Drupal::entityQuery('digital_serial_page')
      ->condition('parent_issue', $this->id());
    return $query->execute();
  }

}
