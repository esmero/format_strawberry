<?php

namespace Drupal\format_strawberryfield\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenOffCanvasDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Language\Language;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Twig\Error\Error as TwigError;
use Drupal\strawberryfield\Tools\StrawberryfieldJsonHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the MetadataDisplayEntity entity edit forms.
 *
 * @ingroup format_strawberryfield
 */
class MetadataDisplayForm extends ContentEntityForm {

  /**
   * Formatter Plugin Manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $formatterPluginManager;

  /**
   * The Twig environment.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected $twig;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->formatterPluginManager = $container->get('plugin.manager.field.formatter');
    $instance->twig = $container->get('twig');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['langcode'] = [
      '#title' => $this->t('Language'),
      '#type' => 'language_select',
      '#default_value' => $this->entity->getUntranslated()->language()->getId(),
      '#languages' => Language::STATE_ALL,
    ];

    $form['footer']['help'] = [
      '#title' => $this->t('Help? Full list of available Twig replacements and functions in Drupal 8.'),
      '#type' => 'link',
      '#url' => Url::fromUri('https://www.drupal.org/docs/8/theming/twig/functions-in-twig-templates',
        [
          'attributes' =>
            [
              'target' => '_blank',
              'rel' => 'nofollow',
            ],
        ]
      ),
    ];

    // Display a Preview feature.
    $form['preview'] = [
      '#attributes' => ['id' => 'metadata-preview-container'],
      '#type' => 'details',
      '#title' => $this->t('Preview'),
      '#open' => FALSE,
    ];
    $form['preview']['ado_context_preview'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('ADO to preview'),
      '#description' => $this->t('The ADO used to preview the data.'),
      '#target_type' => 'node',
      '#maxlength' => 1024,
      '#selection_handler' => 'default:nodewithstrawberry',
    ];
    $form['preview']['button_preview'] = [
      '#type' => 'button',
      '#op' => 'preview',
      '#value' => $this->t('Show preview'),
      '#ajax' => [
        'callback' => [$this, 'ajaxPreview'],
      ],
      '#states' => [
        'visible' => ['input[name="ado_context_preview"' => ['filled' => true]],
      ],
    ];
    $form['preview']['render_native'] = [
      '#type' => 'checkbox',
      '#defaut_value' => FALSE,
      '#title' => 'Show Preview using native Output Format (e.g HTML)',
      '#states' => [
        'visible' => ['input[name="ado_context_preview"' => ['filled' => true]],
      ],
    ];

    // Enable autosaving in code mirror.
    $form['#attached']['library'][] = 'format_strawberryfield/code_mirror_autosave';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    try {
      if (isset($form_state->getTriggeringElement()['#op']) && $form_state->getTriggeringElement()['#op']!='preview') {
        $build = [
          '#type'     => 'inline_template',
          '#template' => $form_state->getValue('twig')[0]['value'],
          '#context'  => ['data' => []],
        ];
        $this->renderer->renderPlain($build);
      }
    }
    catch (\Exception $exception) {
      $message = 'Error in parsing the template';
      // Make the Message easier to read for the end user
      if ($exception instanceof TwigError) {
        $message = $exception->getRawMessage() . ' at line ' . $exception->getTemplateLine();
      } else {
        $message = $exception->getMessage();
      }
      // Do not set Form Errors if running a Preview Operation.
      if (isset($form_state->getTriggeringElement()['#type']) &&
        $form_state->getTriggeringElement()['#type'] == 'submit') {
        // This is not showing correctly. Why is the message missing?
        $this->messenger()->addError($message);
        $form_state->setErrorByName('twig', $message);
      }
    }
    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\format_strawberryfield\MetadataDisplayInterface $entity */
    $entity = $this->entity;
    $entity->setNewRevision();
    $status = parent::save($form, $form_state);
    if ($status == SAVED_UPDATED) {
      $this->messenger()->addMessage($this->t('The Metadata Display %entity has been updated.', ['%entity' => $entity->toLink()->toString()]));
    }
    else {
      $this->messenger()->addMessage($this->t('The Metadata Display %entity has been added.', ['%entity' => $entity->toLink()->toString()]));
    }
    $this->formatterPluginManager->clearCachedDefinitions();
    $this->twig->invalidate();

    return $status;
  }

  /**
   * AJAX callback.
   */
  public static function ajaxPreview($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    /** @var \Drupal\format_strawberryfield\MetadataDisplayInterface $entity */
    $entity = $form_state->getFormObject()->getEntity();

    // Attach the library necessary for using the OpenOffCanvasDialogCommand and
    // set the attachments for this Ajax response.
    $form['#attached']['library'][] = 'core/drupal.dialog.off_canvas';
    $form['#attached']['library'][] = 'codemirror_editor/editor';
    $response->setAttachments($form['#attached']);


    if (!empty($form_state->getValue('ado_context_preview'))) {
      /** @var \Drupal\node\NodeInterface $preview_node */
      $preview_node = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load($form_state->getValue('ado_context_preview'));
      if (empty($preview_node)) {
        return $response;
      }
      // Check if render native is requested and get mimetype
      $mimetype = $form_state->getValue('mimetype');
      $mimetype = !empty($mimetype) ? $mimetype[0]['value'] : 'text/html';
      $show_render_native = $form_state->getValue('render_native');

      $sbf_fields = \Drupal::service('strawberryfield.utility')
        ->bearsStrawberryfield($preview_node);

      // Set initial context.
      $context = [
        'node' => $preview_node,
        'iiif_server' => \Drupal::service('config.factory')
          ->get('format_strawberryfield.iiif_settings')
          ->get('pub_server_url'),
      ];

      // Add the SBF json context.
      // @see MetadataExposeDisplayController::castViaTwig()
      foreach ($sbf_fields as $field_name) {
        /** @var \Drupal\strawberryfield\Field\StrawberryFieldItemList $field */
        $field = $preview_node->get($field_name);
        foreach ($field as $offset => $fielditem) {
          $jsondata = json_decode($fielditem->value, TRUE);
          // Preorder as:media by sequence.
          $ordersubkey = 'sequence';
          foreach (StrawberryfieldJsonHelper::AS_FILE_TYPE as $key) {
            StrawberryfieldJsonHelper::orderSequence($jsondata, $key, $ordersubkey);
          }
          if ($offset === 0) {
            $context['data'] = $jsondata;
          }
          else {
            $context['data'][$offset] = $jsondata;
          }
        }
      }

      $output = [];
      $output['json'] = [
        '#type' => 'details',
        '#title' => t('JSON Data'),
        '#open' => FALSE,
      ];
      $output['json']['data'] = [
        '#type' => 'codemirror',
        '#rows' => 60,
        '#value' => json_encode($context['data'], JSON_PRETTY_PRINT),
        '#codemirror' => [
          'lineNumbers' => FALSE,
          'toolbar' => FALSE,
          'readOnly' => TRUE,
          'mode' => 'application/json',
        ],
      ];

      // Try to Ensure we're using the twig from user's input instead of the entity's
      // default.
      try {
        $input = $form_state->getUserInput();
        $entity->set('twig', $input['twig'][0], FALSE);
        $render = $entity->renderNative($context);
        if ($show_render_native) {
          $message = '';
          switch ($mimetype) {
            case 'application/ld+json':
            case 'application/json':
              json_decode((string) $render);
              if (JSON_ERROR_NONE !== json_last_error()) {
                throw new \Exception(
                  'Error parsing JSON: ' . json_last_error_msg(),
                  0,
                  null
                );
              }
              break;
            case 'text/html':
              libxml_use_internal_errors(true);
              $dom = new \DOMDocument('1.0', 'UTF-8');
              if ($dom->loadHTML((string) $render)) {
                if ($error = libxml_get_last_error()) {
                  libxml_clear_errors();
                  $message = $error->message;
                }
                break;
              }
              else {
                throw new \Exception(
                  'Error parsing HTML',
                  0,
                  null
                );
              }
            case 'application/xml':
              libxml_use_internal_errors(true);
              try {
                libxml_clear_errors();
                $dom = new \SimpleXMLElement((string) $render);
                if ($error = libxml_get_last_error()) {
                  $message = $error->message;
                }
              } catch (\Exception $e) {
                throw new \Exception(
                  "Error parsing XML: {$e->getMessage()}",
                  0,
                  null
                );
              }
              break;
          }
        }
        if (!$show_render_native || ($show_render_native && $mimetype != 'text/html')) {
          $output['preview'] = [
            '#type' => 'codemirror',
            '#rows' => 60,
            '#value' => $render,
            '#codemirror' => [
              'lineNumbers' => FALSE,
              'toolbar' => FALSE,
              'readOnly' => TRUE,
              'mode' => $mimetype,
            ],
          ];
        }
        else {
          $output['preview'] = [
            '#type' => 'details',
            '#open' => TRUE,
            '#title' => 'HTML Output',
            'messages' => [
              '#markup' => $message,
              '#attributes' => [
                'class' => ['error'],
              ],
            ],
            'render' => [
                '#markup' => $render,
            ],
          ];
        }
      } catch (\Exception $exception) {
        // Make the Message easier to read for the end user
        if ($exception instanceof TwigError) {
          $message = $exception->getRawMessage() . ' at line ' . $exception->getTemplateLine();
        }
        else {
          $message = $exception->getMessage();
        }

        $output['preview'] = [
          '#type' => 'details',
          '#open' => TRUE,
          '#title' => t('Syntax error'),
          'error' => [
            '#markup' => $message,
          ]
        ];
      }
      $response->addCommand(new OpenOffCanvasDialogCommand(t('Preview'), $output, ['width' => '50%']));
    }
    // Always refresh the Preview Element too.
    $form['preview']['#open'] = TRUE;
    $response->addCommand(new ReplaceCommand('#metadata-preview-container', $form['preview']));
    \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_STATUS);
    if ($form_state->getErrors()) {
      // Clear errors so the user does not get confused when reloading.
      \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_ERROR);

      $form_state->clearErrors();
    }
    return $response;
  }
}
