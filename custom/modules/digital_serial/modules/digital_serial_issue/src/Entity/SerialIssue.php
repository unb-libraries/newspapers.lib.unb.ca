<?php

namespace Drupal\digital_serial_issue\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\user\UserInterface;
use Drupal\digital_serial_page\Entity\SerialPageInterface;

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
 *     "canonical" = "/admin/structure/digital_serial_issue/{digital_serial_issue}",
 *     "add-form" = "/admin/structure/digital_serial_issue/add",
 *     "edit-form" = "/admin/structure/digital_serial_issue/{digital_serial_issue}/edit",
 *     "delete-form" = "/admin/structure/digital_serial_issue/{digital_serial_issue}/delete",
 *     "collection" = "/admin/structure/digital_serial_issue",
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
  public function hasCabinetModule(CabinetModuleInterface $module) {
    $module_ids = [];
    foreach ($this->getCabinetModules() as $stored_module) {
      if ($stored_module->id() == $module->id()) {
        return TRUE;
      }
    };
    return FALSE;
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
        'max_length' => 50,
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
        'max_length' => 16,
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

    $fields['issue_issue'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Issue'))
      ->setDescription(t('Issue number of the issue.'))
      ->setSettings([
        'max_length' => 16,
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

    $fields['issue_edition'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Edition'))
      ->setDescription(t('Edition of the issue.'))
      ->setSettings([
        'max_length' => 50,
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
        'max_length' => 50,
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

    $fields['issue_pages'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Pages'))
      ->setDescription(t('Pages in this issue.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSettings(
        [
          'target_type' => 'digital_serial_page',
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
  public function getIssuePages() {
    return $this->get('issue_pages')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function hasPage(SerialPageInterface $page) {
    foreach ($this->getIssuePages() as $stored_page) {
      if ($stored_page->id() == $page->id()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addPage(SerialPageInterface $page) {
    return $this->get('issue_pages')->appendItem($page);
  }

  /**
   * {@inheritdoc}
   */
  public function getPageIds() {
    $page_ids = [];
    foreach ($this->getIssuePages() as $page) {
      $page_ids[] = $page->id();
    }
    return $page_ids;
  }

}
