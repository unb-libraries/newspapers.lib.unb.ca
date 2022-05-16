<?php

namespace Drupal\serial_holding\Entity;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\link\LinkItemInterface;
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
 *     "label" = "holding_coverage",
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
    $this->set('holding_start_date', $date->format("Y-m-d"));
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
    $this->set('holding_end_date', $date->format("Y-m-d"));
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
  public function getHoldingLocation() {
    return $this->get('holding_location')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setHoldingLocation($location) {
    $this->set('holding_location', $location);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHoldingCallNumber() {
    return $this->get('holding_call_no')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setHoldingCallNumber($call_no) {
    $this->set('holding_call_no', $call_no);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHoldingRetentionPeriod() {
    return $this->get('holding_retention')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setHoldingRetentionPeriod($retention) {
    $this->set('holding_retention', $retention);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHoldingFiledAs() {
    return $this->get('holding_filed_as')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setHoldingFiledAs($filed_as) {
    $this->set('holding_filed_as', $filed_as);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHoldingLastReceived() {
    return $this->get('holding_last_rec')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setHoldingLastReceived($last_rec) {
    $this->set('holding_last_rec', $last_rec);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHoldingUri() {
    return $this->get('holding_uri')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setHoldingUri($uri) {
    $this->set('holding_uri', $uri);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHoldingNotes() {
    return $this->get('holding_notes')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getMicroformType() {
    return $this->get('holding_microform_type')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setHoldingNotes($notes) {
    $this->set('holding_notes', $notes);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStartDateEmbargo() {
    return $this->get('holding_start_date_embargo')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function hasStartDateEmbargo() {
    return !empty($this->get('holding_start_date_embargo')->value);
  }

  /**
   * {@inheritdoc}
   */
  public function getEndDateEmbargo() {
    return $this->get('holding_end_date_embargo')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function hasEndDateEmbargo() {
    return !empty($this->get('holding_end_date_embargo')->value);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstitution() {
    return $this->get('holding_institution')->entity;
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
      ->setTranslatable(TRUE);

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
          'weight' => -100,
        ]
      )
      ->setDisplayOptions(
        'form',
        [
          'type' => 'options_select',
          'weight' => -100,
        ]
      );

    $fields['holding_microform_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Microform Type'))
      ->setDescription(t('Select a microform type option.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setSettings([
        'allowed_values' => [
          'neg' => 'negative archival master',
          'pos' => 'positive copy',
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['holding_institution'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Institution'))
      ->setDescription(t('Institution options are automatically filtered as you type.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'institution' => 'institution',
          ],
        ],
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -95,
      ]);

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
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'settings' => [
          'format_type' => 'html_date',
        ],
        'weight' => -80,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime',
        'weight' => -80,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['holding_start_date_embargo'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Holding Start Date Embargo Code'))
      ->setDescription(t('Enter the start date embargo code, if any.'))
      ->setSettings([
        'max_length' => 256,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -79,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -79,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['holding_end_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Holding End Date'))
      ->setDescription(t('The serial holding end date. NOTE : If holding is "ongoing", leave this empty.'))
      ->setRevisionable(FALSE)
      ->setSettings([
        'datetime_type' => 'date',
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'settings' => [
          'format_type' => 'html_date',
        ],
        'weight' => -70,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime',
        'weight' => -70,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['holding_end_date_embargo'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Holding End Date Embargo Code'))
      ->setDescription(t('Enter the end date embargo code, if any.'))
      ->setSettings([
        'max_length' => 256,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -69,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -69,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['holding_coverage'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Holding Coverage Statement'))
      ->setDescription(t('Enter the holding coverage statement. Ex: July 1, 1869 - June 1871; Jan 1872 - Sept 6, 1873'))
      ->setSettings([
        'max_length' => 512,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -60,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -60,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    /* Missing Holdings field uses Allowed Formats contrib/Third Party Settings */
    /* Therefore configured via repo's field.field.serial_holding and */
    /* core.entity_form_display.serial_holding config YAML */

    $fields['holding_location'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Location'))
      ->setDescription(t('Enter the location. Ex: ENG-STACKS or HIL-STORN'))
      ->setSettings([
        'max_length' => 256,
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

    $fields['holding_call_no'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Call Number'))
      ->setDescription(t('Enter the call number. Ex: PR 8923 W6 L36 1990 c.3'))
      ->setSettings([
        'max_length' => 256,
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

    $fields['holding_retention'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Retention Period'))
      ->setDescription(t('Enter the retention period.'))
      ->setSettings([
        'max_length' => 256,
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

    $fields['holding_filed_as'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Filed As'))
      ->setDescription(t('Enter the filed as value.'))
      ->setSettings([
        'max_length' => 256,
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

    $fields['holding_last_rec'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Last Received'))
      ->setDescription(t('Enter the date this holding was last received.'))
      ->setSettings([
        'max_length' => 256,
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

    $fields['holding_uri'] = BaseFieldDefinition::create('link')
      ->setLabel(t('URI'))
      ->setDescription(t('Enter the URI pointing to the digital holding.'))
      ->setSettings([
        'link_type' => LinkItemInterface::LINK_GENERIC,
        'title' => DRUPAL_DISABLED,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['holding_digital_title'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Linked Digital Title'))
      ->setDescription(t('Digital Title this issue belongs to'))
      ->setSettings(
        [
          'target_type' => 'digital_serial_title',
          'handler' => 'default',
        ]
      );

    $fields['holding_notes'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('Notes related to this holding.'))
      ->setSettings([
        'default_value' => '',
        'max_length' => 2048,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_long',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_long',
        'text_processing' => 0,
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
