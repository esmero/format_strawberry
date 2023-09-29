<?php

namespace Drupal\format_strawberryfield\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Twig\Node\ModuleNode;
use Twig\Node\BodyNode;
use Twig\Node\Node;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use InvalidArgumentException;
use Drupal\format_strawberryfield\MetadataDisplayInterface;
use Drupal\user\UserInterface;
use Twig\Source;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\Unary\NotUnary;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\GetAttrExpression;

/**
 * Defines the Metadata Display Content entity.
 *
 * @ingroup format_strawberryfield
 *
 * This is the main definition of the entity type. From it, an entityType is
 * derived. The most important properties in this example are listed below.
 *
 * id: The unique identifier of this entityType. It follows the pattern
 * 'moduleName_xyz' to avoid naming conflicts.
 *
 * label: Human readable name of the entity type.
 *
 * handlers: Handler classes are used for different tasks. You can use
 * standard handlers provided by D8 or build your own, most probably derived
 * from the standard class. In detail:
 *
 * - view_builder: we use the standard controller to view an instance. It is
 *   called when a route lists an '_entity_view' default for the entityType
 *   (see routing.yml for details. The view can be manipulated by using the
 *   standard drupal tools in the settings.
 *
 * - list_builder: We derive our own list builder class from the
 *   entityListBuilder to control the presentation.
 *   If there is a view available for this entity from the views module, it
 *   overrides the list builder. @todo: any view? naming convention?
 *
 * - form: We derive our own forms to add functionality like additional fields,
 *   redirects etc. These forms are called when the routing list an
 *   '_entity_form' default for the entityType. Depending on the suffix
 *   (.add/.edit/.delete) in the route, the correct form is called.
 *
 * - access: Our own accessController where we determine access rights based on
 *   permissions.
 *
 * More properties:
 *
 *  - base_table: Define the name of the table used to store the data. Make sure
 *    it is unique. The schema is automatically determined from the
 *    BaseFieldDefinitions below. The table is automatically created during
 *    installation.
 *
 *  - fieldable: Can additional fields be added to the entity via the GUI?
 *    Analog to content types.
 *
 *  - entity_keys: How to access the fields. Analog to 'nid' or 'uid'.
 *
 *  - links: Provide links to do standard tasks. The 'edit-form' and
 *    'delete-form' links are added to the list built by the
 *    entityListController. They will show up as action buttons in an additional
 *    column.
 *
 * There are many more properties to be used in an entity type definition. For
 * a complete overview, please refer to the '\Drupal\Core\Entity\EntityType'
 * class definition.
 *
 * The following construct is the actual definition of the entity type which
 * is read and cached. Don't forget to clear cache after changes.
 *
 * @ContentEntityType(
 *   id = "metadatadisplay_entity",
 *   label = @Translation("Metadata Processor Entity"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\format_strawberryfield\Entity\Controller\MetadataDisplayListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\format_strawberryfield\Form\MetadataDisplayForm",
 *       "edit" = "Drupal\format_strawberryfield\Form\MetadataDisplayForm",
 *       "delete" = "Drupal\format_strawberryfield\Form\MetadataDisplayDeleteForm",
 *     },
 *     "access" = "Drupal\format_strawberryfield\MetadataDisplayAccessControlHandler",
 *   },
 *   base_table = "strawberryfield_metadatadisplay",
 *   admin_permission = "administer metadatadisplay entity",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "published" = "status",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *   },
 *   links = {
 *     "canonical" = "/metadatadisplay/{metadatadisplay_entity}",
 *     "edit-form" = "/metadatadisplay/{metadatadisplay_entity}/edit",
 *     "delete-form" = "/metadatadisplay/{metadatadisplay_entity}/delete",
 *     "collection" = "/metadatadisplay/list"
 *   },
 *   field_ui_base_route = "format_strawberryfield.metadatadisplay_settings",
 *   revision_table = "metadatadisplay_entity_revision",
 *   revision_data_table = "metadatadisplay_entity_field_revision",
 *   show_revision_ui = TRUE,
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message",
 *   },
 * )
 *
 * The 'links' above are defined by their path. For core to find the
 * route, the route name must follow the correct pattern:
 *
 * entity.<entity-name>.<link-name> (replace dashes with underscores)
 * Example: 'entity.content_entity_example_contact.canonical'
 *
 * See routing file above for the corresponding implementation
 *
 * This class defines methods and fields for the  Metadata Display Entity
 *
 * Being derived from the ContentEntityBase class, we can override the methods
 * we want. In our case we want to provide access to the standard fields about
 * creation and changed time stamps.
 *
 * MetadataDisplayInterface also exposes the EntityOwnerInterface.
 * This allows us to provide methods for setting and providing ownership
 * information.
 *
 * The most important part is the definitions of the field properties for this
 * entity type. These are of the same type as fields added through the GUI, but
 * they can by changed in code. In the definition we can define if the user with
 * the rights privileges can influence the presentation (view, edit) of each
 * field.
 */
class MetadataDisplayEntity extends EditorialContentEntityBase implements MetadataDisplayInterface {

  // Implements methods defined by EntityChangedInterface.
  use EntityChangedTrait;
  use UseCacheBackendTrait;
  use LoggerChannelTrait;

  CONST CACHE_TAG_ID = 'format_strawberry:metadata_display_related_tags:';

  /**
   * Calculated Twig vars used by this template.
   *
   * @var array|null
   */
  protected $usedTwigVars = NULL;

  /**
   * {@inheritdoc}
   *
   * When a new entity instance is added, set the user_id entity reference to
   * the current user as the creator of the instance.
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
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update); // TODO: Change the autogenerated stub
    // Calculate RelatedCacheTags.
    try {
      $this->getRelatedCacheTagsToInvalidate(TRUE);
    }
    catch (\Exception $exception) {
      $this->getLogger('format_strawberryfield')->error('Could not calculate related Cache tags. Check your Twig template syntax/use of external views for Metadata Display with ID @id',['@id' => $this->id()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(EntityStorageInterface $storage) {
    parent::postCreate($storage); // TODO: Change the autogenerated stub
    // Calculate RelatedCacheTags.
    $this->getRelatedCacheTagsToInvalidate(TRUE);
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
   * Returns the Twig Environment.
   *
   * @return \Drupal\Core\Template\TwigEnvironment|mixed
   *   The Drupal Twig Service.
   */
  public function twigEnvironment() {
    return \Drupal::service('twig');
  }

  /**
   * {@inheritdoc}
   *
   * Define the field properties here.
   *
   * Field name, type and size determine the table structure.
   *
   * In addition, we can define how the field and its content can be manipulated
   * in the GUI. The behaviour of the widgets used can be determined here.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);
    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Metadata Display entity.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Metadata Display entity.'))
      ->setReadOnly(TRUE);

    // Name field for the entity.
    // We set display options for the view as well as the form.
    // Users with correct privileges can change the view and edit configuration.
    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Metadata Display entity.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    // Holds the actual Twig template.
    // @TODO see https://twig.symfony.com/doc/2.x/api.html#sandbox-extension
    $fields['twig'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Twig template'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'text_plain',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'settings' => [
          'text_processing' => FALSE,
          'rows' => 10,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE)
      ->addConstraint('NotBlank')
      ->addConstraint('TwigTemplateConstraint', ['useTwigMessage' => FALSE, 'TwigTemplateLogicalName' => 'MetadataDisplayEntity']);

    // Owner field of the Metadata Display Entity.
    // Entity reference field, holds the reference to the user object.
    // The view shows the user name field of the user.
    // The form presents a auto complete field for the user name.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User Name'))
      ->setDescription(t('The Name of the associated user.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setRevisionable(TRUE)
      ->setDescription(t('The language code of Metadata Display entity.'));
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setRevisionable(TRUE)
      ->setDescription(t('The time that the Metadata Display was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setRevisionable(TRUE)
      ->setDescription(t('The time that the Metadata Display was last edited.'));

    $fields['link'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('Metadata Definition Source Link'))
      ->setDescription(t('A link an Ontology, Schema or any other Metadata Definition'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('view', TRUE);

    // What type of output is expected from the twig template processing.
    $fields['mimetype'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Primary mime type this Twig Template entity will generate as output.'))
      ->setDescription(t('When downloading the output, this will define the extension, validation and format. Every Mime type supports also being rendered as HTML'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'default_value' => 'text/html',
        'max_length' => 64,
        'cardinality' => 1,
        'allowed_values' => [
          'text/html' => 'HTML',
          'application/json' => 'JSON',
          'application/ld+json' => 'JSON-LD',
          'application/xml' => 'XML',
          'text/text' => 'TEXT',
          'text/turtle' => 'RDF/TURTLE',
          'text/csv' => 'CSV',
        ],
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->addConstraint('NotBlank');

    $fields['status']->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setSettings(['on_label' => 'Published', 'off_label' => 'Unpublished'])
      ->setDisplayOptions('view', [
        'label' => 'visible',
        'type' => 'boolean',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function processHtml(array $context) {
    if (!isset($context['data']) || !is_array($context['data']) || empty($context['data'])) {
      throw new InvalidArgumentException('Processing a Metadata Display requires a valid "data" structure to be passed');
    }
    $context = $context + $this->getTwigDefaultContext();
    $twigtemplate = $this->get('twig')->getValue();
    $twigtemplate = !empty($twigtemplate) ? $twigtemplate[0]['value'] : "{{ 'empty' }}";
    // @TODO should we have a custom theme hint here?
    $node = $context['node'] ?? NULL;
    $nodeid = $node instanceof FieldableEntityInterface ? $node->id() : NULL;
    if ($nodeid) {
      $cache_tags = Cache::mergeTags(
        $this->getCacheTags(),
        ['node_metadatadisplay:'. $nodeid]
      );
    }
    else {
      $cache_tags = $this->getCacheTags();
    }
    $templaterenderelement = [
      '#type' => 'inline_template',
      '#template' => $twigtemplate,
      '#context' => $context,
      '#cache' => [
        'tags' => $cache_tags
      ],
    ];

    return $templaterenderelement;
  }

  /**
   * {@inheritdoc}
   */
  public function renderNative(array $context) {
    // Note about this method
    // Symfony/Drupal render pipeline uses something named Render Context
    // Twig templates, while being processed & depending on what is being used.
    // Inside them (e.g URL, etc) can generated Cacheable/Bubbleable
    // Metadata. If That happens, the render pipeline will complain about us
    // leaking that metadata/early rendering when using it in a Cacheable
    // response Controller.
    // @See \Drupal\format_strawberryfield\Controller\MetadataExposeDisplayController()
    // Complement $context with default one.
    $context = $context + $this->getTwigDefaultContext();
    $twigtemplate = $this->get('twig')->getValue();
    $twigtemplate = !empty($twigtemplate) ? $twigtemplate[0]['value'] : "{{ 'empty' }}";
    $rendered = $this->twigEnvironment()->renderInline(
      $twigtemplate,
      $context
    );

    return $rendered;
  }

  /**
   * {@inheritdoc}
   */
  public function getTwigVariablesUsed() {
    if ($this->usedTwigVars == NULL) {
      $twigtemplate = $this->get('twig')->getValue();
      $twigtemplate = !empty($twigtemplate) ? $twigtemplate[0]['value'] : "{{ 'empty' }}";
      // Create a \Twig Source first.
      $source = new Source($twigtemplate, $this->label(), '');
      $tokens = $this->twigEnvironment()->tokenize($source);
      $nodes = $this->twigEnvironment()->parse($tokens);
      $used_vars = $this->getTwigVariableNames($nodes,[]);
      ksort($used_vars);
      $this->usedTwigVars = $used_vars;
    }
    return $this->usedTwigVars;
  }

  /**
   * Fetches recursively Twig Template Variables.
   *
   * @param \Twig\Node\ModuleNode $nodes
   *   A Twig Module Nodes object.
   *
   * @param \Twig\Node\BodyNode $nodes
   *   A Twig Module Nodes object.
   *
   * @param \Twig\Node\Node $nodes
   *   A Twig Module Nodes object.
   *
   * @param array $all_variables
   *   An array to track the variables during recursion to return the accumulated line numbers.
   *
   * @param array $set_var
   *   A string to track variables that reference JSON keys.
   *
   * @param array $set_source
   *   An string to track the referenced JSON key for the above.
   *
   * @return array
   *   A list of used $variables by this template.
   */
  private function getTwigVariableNames(ModuleNode|Node|BodyNode $nodes, array $all_variables, string $set_var = '', string $set_source = ''): array {
    $variables = [];
    foreach ($nodes as $node) {
      $lineno = [$node->getTemplateLine()];
      $variable_key = '';
      $parent_path = '';
      if ($node instanceof NameExpression && !$nodes instanceof NotUnary) {
        if (!$node->hasAttribute('name')) {
          continue;
        }
        $variable_key = $node->getAttribute('name');
        if ($variable_key == '_key') {
          $seq = $nodes->getNode('seq');
          if ($seq->hasNode('node') && $seq->getNode('node')->hasAttribute('name')) {
            $seq_name = $seq->getNode('node')->getAttribute('name');
          }
          elseif ($seq->hasAttribute('name')) {
            $seq_name = $seq->getAttribute('name');
          }
          else {
            continue;
          }
          $seq_value = $seq->hasNode('attribute') ? $seq->getNode('attribute')->getAttribute('value') : '';
          $value_target = $nodes->getNode('value_target');
          if (!$value_target->hasAttribute('name')) {
            continue;
          }
          $variable_key = $value_target->getAttribute('name');
          $parent_path = empty($seq_value) ? $seq_name : $seq_name . '.' . $seq_value;
          $set_var = $variable_key;
          $set_source = $parent_path;
        }
      }
      elseif ($node instanceof ConstantExpression && $nodes instanceof GetAttrExpression) {
        $variable_key = $node->getAttribute('value');
      }
      elseif ($node instanceof GetAttrExpression) {
        // The array_unique/array_merge pattern below can't be used here because
        // they don't work on multidimensional arrays. Instead the lines from
        // $all_variables is passed to $variables below, and then the merged
        // lines replace the old values and pass them thru the recursion.
        // @todo: Look deeper into this and find a simpler, cleaner way if possible.
        // At the very least some of this is overkill.
        $variable_names = $this->getTwigVariableNames($node, array_replace_recursive($all_variables, $variables), $set_var, $set_source);
        $variable_names_flat = [];
        $prev_name = null;
        foreach ($variable_names as $name) {
          $name_check = $prev_name ? $prev_name['path'] . '.' . $name['path']:'';
          if (($name['path'] == $set_var) && !($set_source == $name_check)) {
            $variable_names_flat[] = $set_source;
          }
          else {
            $variable_names_flat[] = $name['path'];
          }
          $prev_name = $name;
        }
        $variable_key = implode('.', $variable_names_flat);
      }
      elseif ($node instanceof Node) {
        // See above comment about the recursion.
        $add_variables = $this->getTwigVariableNames($node, array_replace_recursive($all_variables, $variables), $set_var, $set_source);
        $variables = array_replace_recursive($variables, $add_variables);
      }
      if (!empty($variable_key)) {
        $variables[$variable_key]['path'] = $variable_key;
        if(isset($all_variables[$variable_key]['line'])) {
          // Since $variables is lost with each return and $all_variables accumulates
          // line numbers across recursions we need to merge the current line
          // number with those passed thru recursions, and since duplicate
          // values can occur because it's an array, we need unique values.
          $variables[$variable_key]['line']  = array_unique(array_merge($all_variables[$variable_key]['line'], $lineno));
        }
        if(!isset($variables[$variable_key]['line'])) {
          $variables[$variable_key]['line'] = $lineno;
        }
        if (!empty($parent_path)) {
          $variables[$variable_key]['parent_path'] = $parent_path;
        }
      }
    }
    return $variables;
  }

  /**
   * Provides default Context so we can get common values.
   *
   * @return array
   *   Array of the default context.
   */
  private function getTwigDefaultContext() {
    $context = [];
    $context['datafixed'] = [];
    foreach ($this->getFieldDefinitions() as $field) {
      /** @var \Drupal\Core\Field\FieldDefinitionInterface $field */
      // If someone bundled this entity with a strawberryfield_field push the
      // data into context.
      if ($field->getType() == 'strawberryfield_field') {
        foreach ($this->get($field->getName()) as $item) {
          /** @var \Drupal\strawberryfield\Plugin\Field\FieldType\StrawberryFieldItem $item */
          $context['datafixed'] = $context['datafixed'] + $item->provideDecoded(FALSE);
        }
      }
    }

    $context['is_front'] = \Drupal::service('path.matcher')->isFrontPage();
    $context['language'] = \Drupal::languageManager()->getCurrentLanguage();
    $user = \Drupal::currentUser();
    $context['is_admin'] = $user->hasPermission('access administration pages');
    $context['logged_in'] = $user->isAuthenticated();
    return $context;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    if ($this->isNew()) {
      return [];
    }
    $related_tags = $this->getRelatedCacheTagsToInvalidate() ?? [];
    return Cache::mergeTags([$this->entityTypeId . ':' . $this->id()], $related_tags);
  }


  /**
   * Calculates or Returns cached related Cache tags.
   *
   * @param bool $force
   *
   * @return array
   * @throws \Twig\Error\SyntaxError
   */
  public function getRelatedCacheTagsToInvalidate(bool $force = FALSE) {
    $cache_id = static::CACHE_TAG_ID . $this->id();
    if (!$force) {
      $cached = $this->cacheGet($cache_id);
      if ($cached) {
        return $cached->data;
      }
    }
    $twigtemplate = $this->get('twig')->getValue();
    $twigtemplate = !empty($twigtemplate) ? $twigtemplate[0]['value'] : "{{ 'empty' }}";
    // Create a \Twig Source first.
    $source = new Source($twigtemplate, $this->label() ?? $this->uuid(), '');
    $tokens = $this->twigEnvironment()->tokenize($source);
    // I have two options here: i can use the tokens, iterate over each and fetch test them against type 5 and drupal_view name
    // then, if correct of course/ fetch the (, skip it, fetch the next one, fetch the , skip it, fetch the next one (2 arguments)
    // OR, i can let the nodes to be generated and then go recursive... which is slower but safer
    // Only issue i see with first approach is the edge case where a views name/view display or both are variables/not fixed values
    // Pretty sure the tokens won't resolve values at this stage.
    // But i could do a first pass to see if even used at all. And only do expensive Nodes traversal IF
    // And only IF i found `drupal_views` used somewhere? Wonder if the extra code is worth it
    $nodes = $this->twigEnvironment()->parse($tokens);
    $cache_tags = [];
    foreach ($nodes as $node) {
      $cache_tags = array_merge($this->generateCacheTagsFromRelated($node), $cache_tags);
    }
    // Bit convoluted but the cache tags are cached using almost the same cache tags calculated. Means if a View used here changes, we need to recalculate the cache tags
    $this->cacheSet($cache_id, $cache_tags, CacheBackendInterface::CACHE_PERMANENT,  Cache::mergeTags(Cache::mergeTags([$this->entityTypeId . ':' . $this->id()], $cache_tags), [$cache_id]));
    return $cache_tags;
  }

  /**
   * calculates related Cache tags.
   *
   * @param \Twig\Node\Node $node
   *
   * @return array
   */
  private function generateCacheTagsFromRelated(Node $node) {
    // Process nodes that are function expressions
    $tags = [];
    if ($node instanceof \Twig\Node\Expression\FunctionExpression) {
      // Check the function name
      if ($node->getAttribute('name') == 'drupal_view') {
        // Grab the argument
        $arguments_parsed = [];
        // Only get the first 2 ones. If there is a syntax error/missing arguments etc/skip
        $i = 0;
        foreach ($node->getNode('arguments') as $argument) {
          if ($i < 2) {
            // This piece here can not be run outside the $sandbox extension
            // Running the code would be crazy complex
            // So we only accept static strings as arguments
            // Which means using variables for Views/Displays
            // Will excluse those from being accounted as cache-able dependencies
            /* $resolved = eval(
              'return ' . $this->twigEnvironment()->compile($argument) . ';'
            ); */
            if ($argument->hasAttribute('value')) {
              $arguments_parsed[] = $argument->getAttribute('value');
            }
          }
          $i++;
        }
        $arguments_parsed = array_filter($arguments_parsed);
        // Probably better to solve the cache tags right here.
        if (count($arguments_parsed) == 2) {
          /* @var \Drupal\views\ViewEntityInterface|null $view */
          try {
            $view = $this->entityTypeManager()->getStorage('view')->load(
              $arguments_parsed[0]
            );
            if ($view) {
              $tags = array_merge($view->getCacheTagsToInvalidate(), $tags);
              $display = $view->getDisplay($arguments_parsed[1]);
              // We could/should inject views.executable container
              // But it would also imply an overkill of an unused service
              // during any other operation.
              $executable = \Drupal::getContainer()->get('views.executable')->get($view);
              $display_object = $executable->getDisplay($display);
              if ($display_object) {
                // We won't fetch context here. The Twig template itself escapes the
                // Original Caching context of a View because its rendered out of scope.
                $tags = array_merge($display_object->getCacheMetadata()->getCacheTags(), $tags);
              }
              if (is_array($display)) {
                $tags = Cache::mergeTags(
                  $this->calculateViewsMetadataDisplayUsage($display),
                  $tags
                );
              }
            }
          } catch (\Exception $e) {
            // Log? Ignore?
          }
        }
      }
    }

    // Recursively loop through the sub nodes.
    foreach ($node as $child) {
      if ($child instanceof \Twig\Node\Node) {
        $tags = array_merge($this->generateCacheTagsFromRelated($child), $tags);
      }
    }
    return array_unique($tags);
  }

  private function calculateViewsMetadataDisplayUsage(array $display) {
    $tags = [];
    if (isset($display['display_options']['fields'])) {
      foreach ($display['display_options']['fields'] as &$field) {
        $metadatadisplayentity_uuid = $field['settings']['metadatadisplayentity_uuid'] ?? NULL;
        $metadatadisplayentity_uuid = is_string($metadatadisplayentity_uuid) ? $metadatadisplayentity_uuid : NULL;
        if ($metadatadisplayentity_uuid) {
          $metadata_display_entities = $this->entityTypeManager()
            ->getStorage('metadatadisplay_entity')
            ->loadByProperties(['uuid' => $metadatadisplayentity_uuid]);
          $metadata_display_entitiy = is_array($metadata_display_entities) ? reset($metadata_display_entities) : NULL;
          if ($metadata_display_entitiy) {
            // We are not going to get here calculated Tags foreach related Template yet
            // @TODO review in the future how much more this will really help v/s remove cache-ability all
            $tags = array_merge($metadata_display_entitiy->getCacheTagsToInvalidate(), $tags);
          }
        }
      }
    }
    return $tags;
  }

}
