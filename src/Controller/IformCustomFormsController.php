<?php

namespace Drupal\iform_custom_forms\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for iform_custom_forms.
 */
class IformCustomFormsController extends ControllerBase {

  /**
   * Creates a parameters form to configure the custom form.
   *
   * Called as an Ajax request when creating a node of the form type given
   * in the $_POST.
   *
   * @returns Symfony\Component\HttpFoundation\Response
   *   Outputs the html and javascript for the parameters form.
   */
  public function ajaxParams() {
    iform_load_helpers(['form_helper']);
    \form_helper::$is_ajax = TRUE;
    $readAuth = \form_helper::get_read_auth($_POST['website_id'], $_POST['password']);

    $html = \form_helper::prebuilt_form_params_form([
      'form' => $_POST['form'],
      'readAuth' => $readAuth,
      'expandFirst' => TRUE,
      'generator' => (isset($_POST['generator'])) ? $_POST['generator'] : 'No generator metatag posted',
    ]);

    \data_entry_helper::$dumped_resources[] = 'jquery';
    \data_entry_helper::$dumped_resources[] = 'jquery_ui';
    $js = \form_helper::dump_javascript(TRUE);

    return new Response($html . $js);
  }

}
