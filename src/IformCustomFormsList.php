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
  private static $submodules;

  /**
   * A list of iform_custom_forms customisations provided by submosules.
   *
   * @var array
   */
  private static $customisations;

  /**
   * A list of asset libraries.
   *
   * @var array
   */
  private static $libraries;

  /**
   * The absolute path to this module.
   *
   * @var string
   */
  private static $absoluteModulePath;

  /**
   * The injected CacheBackendInterface.
   *
   * @var Drupal\Core\Cache\CacheBackendInterface
   */
  private static $cache;

  /**
   * The injected ModuleExtensionList.
   *
   * @var Drupal\Core\Extension\ModuleExtensionList
   */
  private static $moduleExtensionList;

  /**
   * The injected entityTypeManager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private static $entityTypeManager;

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
    $this->createAbsoluteModulePath();
    $this->createSubmodulesList();
    $this->createCustomisationsList();
    $this->createLibrariesList();
    spl_autoload_register([$this, 'autoloader']);
  }

  /**
   * Sets the absoluteModulePath property.
   */
  protected function createAbsoluteModulePath() {
    if (!isset(self::$absoluteModulePath)) {
      $modulePath = $this->moduleExtensionList->getPath('iform_custom_forms');
      self::$absoluteModulePath = DRUPAL_ROOT . "/$modulePath";
    }
  }

  /**
   * Returns the absolute path of the main module.
   */
  public function getAbsoluteModulePath() {
    return self::$absoluteModulePath;
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

        // Get customisations from all enabled submodules.
        foreach (self::$submodules as $submodule) {
          $subdirs = [
            '.',
            'js',
            'css',
            'lang',
            'templates',
            'validation',
            'extensions',
          ];

          // Check all possible subdirectories.
          foreach ($subdirs as $subdir) {
            self::$customisations[$subdir] = [];
            $relPath = "modules/$submodule/customisations/$subdir";
            $dir = self::$absoluteModulePath . '/' . $relPath;
            if (is_dir($dir)) {
              $handle = opendir($dir);
              // Loop through all the files in the directory.
              while (FALSE !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && !is_dir("$dir/$entry")) {
                  // Store the directory of file relative to main module.
                  self::$customisations[$subdir][$entry] = $relPath;
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
      if (!self::$customisations) {
        // If there are no customisations then the libraries list is empty.
        self::$libraries = [];
      }
      elseif ($cached = $this->cache->get('iform_custom_forms_list_libraries')) {
        // Use list from cache.
        self::$libraries = $cached->data;
      }
      else {
        // Build the list.
        // Load all the nodes of type iform_page.
        $query = $this->entityTypeManager->getStorage('node')->getQuery();
        $nids = $query
          ->condition('type', 'iform_page')
          ->accessCheck(FALSE)
          ->execute();
        $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

        $forms = [];
        foreach ($nodes as $node) {
          $nid = $node->id();
          $lib = "node_$nid";
          $form = $node->field_iform->value;
          $forms[$form][] = $nid;
          $libraries[$lib] = [
            'version' => 'VERSION',
            'js' => [],
            'css' => [
              'base' => [],
            ],
          ];

          // Prebuilt form specific CSS.
          $file = "$form.css";
          if (array_key_exists($file, self::$customisations['css'])) {
            $relPath = self::$customisations['css'][$file];
            $libraries[$lib]['css']['base']["$relPath/$file"] = [];
          }

          // Node specific CSS.
          $file = "node.$nid.css";
          if (array_key_exists($file, self::$customisations['css'])) {
            $relPath = self::$customisations['css'][$file];
            $libraries[$lib]['css']['base']["$relPath/$file"] = [];
          }

          // Prebuilt form specific JS.
          $file = "$form.js";
          if (array_key_exists($file, self::$customisations['js'])) {
            $relPath = self::$customisations['js'][$file];
            $libraries[$lib]['js']["$relPath/$file"] = [];
          }

          // Node specific JS.
          $file = "node.$nid.js";
          if (array_key_exists($file, self::$customisations['js'])) {
            $relPath = self::$customisations['js'][$file];
            $libraries[$lib]['js']["$relPath/$file"] = [];
          }

          // Extension classes can be referred to in the form structure
          // parameter of any page and can contain JS or CSS code in addition to
          // the PHP class.
          if (!empty($node->params['structure'])) {
            // Look for all [extClass.extFunction] in the form structure.
            $pattern = '/\[[a-z_]+\.[a-z_]+\]/';
            if (preg_match_all($pattern, $node->params['structure'], $extensions)) {
              foreach ($extensions[0] as $extension) {
                // Remove the [] from around $extension.
                $extension = substr($extension, 1, -1);
                list($extClass, $extFunction) = explode('.', $extension);
                // Look for js files for each extension.
                $file = "$extClass.js";
                if (array_key_exists($file, self::$customisations['extensions'])) {
                  $relPath = self::$customisations['extensions'][$file];
                  $libraries[$lib]['js']["$relPath/$file"] = [];
                }
                // Look for css files for each extension.
                $file = "$extClass.css";
                if (array_key_exists($file, self::$customisations['extensions'])) {
                  $relPath = self::$customisations['extensions'][$file];
                  $libraries[$lib]['css']['base']["$relPath/$file"] = [];
                }
              }
            }
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

  /**
   * Autoloader for forms and extensions in submodules.
   *
   * The following naming convention exists for Indicia custom forms:
   * a file, "$formName.php", contains a class "iform_$formName".
   * For extensions:
   * a file, "$extName.php", contains a class "extension_$extName".
   */
  protected function autoloader($className) {
    // Return early if we have no customisations.
    if (!self::$customisations) {
      return;
    }

    // Check the class is a candidate for an iform custom form.
    if (substr($className, 0, 6) === 'iform_') {
      $formName = substr($className, 6);
      $fileName = "$formName.php";
      // Check the class file exists in form customisations.
      if (array_key_exists($fileName, self::$customisations['.'])) {
        // Construct the absolute path to the file.
        $path = self::$absoluteModulePath .
                '/' . self::$customisations['.'][$fileName] .
                '/' . $fileName;
        require $path;
      }
    }
    // Or a custom extension.
    elseif (substr($className, 0, 10) === 'extension_') {
      $extName = substr($className, 10);
      $fileName = "$extName.php";
      // Check the class file exists in the extension customisations.
      if (array_key_exists($fileName, self::$customisations['extensions'])) {
        // Construct the absolute path to the file.
        $path = self::$absoluteModulePath .
                '/' . self::$customisations['extensions'][$fileName] .
                '/' . $fileName;
        require $path;
      }
    }
  }

}
