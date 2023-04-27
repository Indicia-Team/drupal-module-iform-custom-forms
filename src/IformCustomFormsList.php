<?php

namespace Drupal\iform_custom_forms;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service providing lists of custom code.
 */
class IformCustomFormsList {

  /**
   * A list of iform_custom_forms submodules which are enabled.
   *
   * @var array
   */
  protected static $submodules;

  /**
   * A list of iform_custom_forms customisations provided by submosules.
   *
   * @var array
   */
  protected static $customisations;

  /**
   * A list of asset libraries.
   *
   * @var array
   */
  protected static $libraries;

  /**
   * The injected CacheBackendInterface.
   *
   * @var Drupal\Core\Cache\CacheBackendInterface
   */
  protected static $cache;

  /**
   * The injected ModuleExtensionList.
   *
   * @var Drupal\Core\Extension\ModuleExtensionList
   */
  protected static $moduleExtensionList;

  /**
   * The injected entityTypeManager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected static $entityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(
    CacheBackendInterface $cache,
    ModuleExtensionList $moduleExtensionList,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->cache = $cache;
    $this->moduleExtensionList = $moduleExtensionList;
    $this->entityTypeManager = $entityTypeManager;
    $this->createSubmodulesList();
    $this->createCustomisationsList();
    $this->createLibrariesList();
  }

  /**
   * Create the list of enabled submodules.
   *
   * The list is an array of the iform_custom_forms submodule names.
   */
  protected function createSubmodulesList() {
    if (!isset(self::$submodules)) {
      // Try to retrieve list from cache.
      $cached = $this->cache->get('iform_custom_forms_list_submodules');
      if ($cached) {
        self::$submodules = $cached->data;
      }
      else {
        // Construct the list if not cached.
        $extensions = $this->moduleExtensionList->getAllInstalledInfo();
        self::$submodules = [];
        foreach ($extensions as $key => $value) {
          if (substr($key, 0, 19) === 'iform_custom_forms_') {
            self::$submodules[] = $key;
          }
        }
        // Save to cache for next time.
        $this->cache->set('iform_custom_forms_list_submodules', self::$submodules);
      }
    }
  }

  /**
   * Returns the list of enabled submodules.
   */
  public function getSubmodules() {
    return self::$submodules;
  }

  /**
   * Create the list of customisations.
   *
   * The list is an associative array keyed by customisation subdirectory.
   * Each value is an associative array keyed by the file names of that type of
   * customisation and having a value which is its directory path.
   */
  protected function createCustomisationsList() {
    if (!isset(self::$customisations)) {
      // Try to retrieve list from cache.
      $cached = $this->cache->get('iform_custom_forms_list_customisations');
      if ($cached) {
        self::$customisations = $cached->data;
      }
      else {
        // Construct the list if not cached.
        self::$customisations = [];
        $modulePath = $this->moduleExtensionList->getPath('iform_custom_forms');
        $moduleFullPath = DRUPAL_ROOT . "/$modulePath";

        // Get customisations from all enabled submodules.
        foreach (self::$submodules as $submodule) {
          $subdirs = [
            'js',
            'css',
            'forms',
            'forms/js',
            'forms/css',
            'lang',
            'validation',
          ];

          // Check all possible subdirectories.
          foreach ($subdirs as $subdir) {
            self::$customisations[$subdir] = [];
            $dir = "$moduleFullPath/modules/$submodule/$subdir";
            if (is_dir($dir)) {
              $handle = opendir($dir);
              while (FALSE !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && !is_dir("$dir/$entry")) {
                  // Store the directory of file relative to main module.
                  self::$customisations[$subdir][$entry] = "modules/$submodule/$subdir";
                }
              }
              closedir($handle);
            }
          }
        }
        // Save to cache for next time.
        $this->cache->set('iform_custom_forms_list_customisations', self::$customisations);
      }
    }
  }

  /**
   * Return the list of customisations.
   */
  public function getCustomisations() {
    return self::$customisations;
  }

  /**
   * Create the list of libraries, that is js and css, for each custom form.
   *
   * The list is in the correct format for Drupal to receive from a call to
   * hook_library_info_build.
   */
  protected function createLibrariesList() {
    if (!isset(self::$libraries)) {
      // Try to retrieve list from cache.
      $cached = $this->cache->get('iform_custom_forms_list_libraries');
      if ($cached) {
        self::$libraries = $cached->data;
      }
      else {
        // Load all the nodes of type iform_page.
        $query = $this->entityTypeManager->getStorage('node')->getQuery();
        $nids = $query
          ->condition('type', 'iform_page')
          ->accessCheck(FALSE)
          ->execute();
        $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

        foreach ($nodes as $node) {
          $nid = $node->id();
          $lib = "node_$nid";
          $form = $node->field_iform->value;
          $libraries[$lib] = [
            'version' => 'VERSION',
            'js' => [],
            'css' => [
              'base' => []
            ]
          ];

          // Prebuilt form specific CSS.
          $file = "$form.css";
          if (array_key_exists($file, self::$customisations['forms/css'])) {
            $dir = self::$customisations['forms/css'][$file];
            $libraries[$lib]['css']['base']["$dir/$file"] = [];
          }

          // Node specific CSS.
          $file = "node.$nid.css";
          if (array_key_exists($file, self::$customisations['css'])) {
            $dir = self::$customisations['css'][$file];
            $libraries[$lib]['css']['base']["$dir/$file"] = [];
          }

          // Prebuilt form specific JS.
          $file = "$form.js";
          if (array_key_exists($file, self::$customisations['forms/js'])) {
            $dir = self::$customisations['forms/js'][$file];
            $libraries[$lib]['js']["$dir/$file"] = [];
          }

          // Node specific JS
          $file = "node.$nid.js";
          if (array_key_exists($file, self::$customisations['js'])) {
            $dir = self::$customisations['js'][$file];
            $libraries[$lib]['js']["$dir/$file"] = [];
          }

          // Skip any unnecessary empty libraries.
          if (empty($libraries[$lib]['css']['base']) && empty($libraries[$lib]['js'])) {
            unset($libraries[$lib]);
          }
        }

        self::$libraries = $libraries;
        // Save to cache for next time.
        $this->cache->set('iform_custom_forms_list_libraries', self::$libraries);
      }
    }
  }

  /**
   * Returns the list of libraries.
   */
  public function getLibraries() {
    return self::$libraries;
  }

}