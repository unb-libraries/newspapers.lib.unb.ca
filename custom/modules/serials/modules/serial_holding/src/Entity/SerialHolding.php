<?php

namespace Drupal\serial_holding\Entity;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Serial holding entity.
 *
 * @ingroup serial_holding
 *
 * @ContentEntityType(
 *   id = "serial_holding",
 *   label = @Translation("Serial holding"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\serial_holding\SerialHoldingListBuilder",
 *     "views_data" = "Drupal\serial_holding\Entity\SerialHoldingViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\serial_holding\Form\SerialHoldingForm",
 *       "add" = "Drupal\serial_holding\Form\SerialHoldingForm",
 *       "edit" = "Drupal\serial_holding\Form\SerialHoldingForm",
 *       "delete" = "Drupal\serial_holding\Form\SerialHoldingDeleteForm",
 *     },
 *     "access" = "Drupal\serial_holding\SerialHoldingAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\serial_holding\SerialHoldingHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "serial_holding",
 *   admin_permission = "administer serial holding entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/serial_holding/{serial_holding}",
 *     "add-form" = "/admin/structure/serial_holding/add",
 *     "edit-form" = "/admin/structure/serial_holding/{serial_holding}/edit",
 *     "delete-form" = "/admin/structure/serial_holding/{serial_holding}/delete",
 *     "collection" = "/admin/structure/serial_holding",
 *   },
 *   field_ui_base_route = "serial_holding.settings"
 * )
 */
class SerialHolding extends ContentEntityBase implements SerialHoldingInterface {

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
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
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
  public function getParentTitle() {
    return $this->get('parent_title')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setParentTitle(NodeInterface $title) {
    $this->set('parent_title', $title->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHoldingType() {
    return $this->get('holding_type')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setHoldingType(TermInterface $type) {
    $this->set('holding_type', $type->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHoldingStartDate() {
    return $this->get('holding_start_date')->date;
  }

  /**
   * {@inheritdoc}
   */
  public function setHoldingStartDate(DrupalDateTime $date) {
    $this->set('holding_start_date', $date);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHoldingEndDate() {
    return $this->get('holding_end_date')->date;
  }

  /**
   * {@inheritdoc}
   */
  public function setHoldingEndDate(DrupalDateTime $date) {
    $this->set('holding_end_date', $date);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHoldingCoverage() {
    return $this->get('holding_coverage')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setHoldingCoverage($coverage) {
    $this->set('holding_coverage', $coverage);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Serial holding entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Serial holding entity.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Serial holding is published.'))
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['holding_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('Select the holding type.'))
      ->setRequired(TRUE)
      ->setSettings(
        [
          'target_type' => 'taxonomy_term',
          'handler' => 'default:taxonomy_term',
          'handler_settings' => [
            'target_bundles' => [
              'serial_holding_types' => 'serial_holding_types',
            ],
          ],
        ]
      )
      ->setDisplayOptions(
        'view',
        [
          'label' => 'above',
          'type' => 'number',
          'weight' => -1,
        ]
      )
      ->setDisplayOptions(
        'form',
        [
          'type' => 'options_select',
          'weight' => -1,
        ]
      );

    $fields['parent_title'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Parent Title'))
      ->setSettings(
        [
          'target_type' => 'node',
          'handler' => 'default',
          'handler_settings' => [
            'target_bundles' => [
              SERIAL_HOLDING_ENTITY_REF_TYPE => SERIAL_HOLDING_ENTITY_REF_TYPE,
            ],
          ],
        ]
      );

    $fields['holding_start_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Holding Start Date'))
      ->setDescription(t('The serial holding start date.'))
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
        'weight' => 15,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['holding_end_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Holding End Date'))
      ->setDescription(t('The serial holding end date.'))
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
        'weight' => 15,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['holding_coverage'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Holding Coverage Statement'))
      ->setDescription(t('Enter the holding coverage statement. Ex: July 1, 1869 - June 1871; Jan 1872 - Sept 6, 1873'))
      ->setSettings([
        'max_length' => 256,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
