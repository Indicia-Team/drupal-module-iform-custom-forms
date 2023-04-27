iForm Custom Forms Module
=========================

This is a Drupal module for managing code for custom Indicia forms.

The [iForm](https://github.com/Indicia-Team/drupal-8-module-iform) module
supports various standard forms but sometimes a project will require something
extra. In order for the iForm module to remain clean and easy to update yet to
ensure the customisations are held under version control, we have created this 
module.

For every new project, create a subfolder of the modules folder named
`iform_custom_forms_<project>` where you replace `<project>` with a relevant, 
unique, project name. Within this folder, you can create subfolders for the 
different possible types of customisation. E.g.

```
iform_custom_forms
|
|__ modules
   |
   |__ iform_custom_forms_ebms
      |
      |__ css
      |__ js
      |__ lang
      |__ templates
      |__ validation
      |__ forms
         |
         |_ css
         |_ js
```
You only need to create the folders you will use.

Within your project folder you must also add a file called 
`iform_custom_forms_<project>.info.yml`. The contents of this file shoud be
similar to the following example.

```
name: EBMS
description: >
  Cusomisations for the European Butterfly Monitoring Scheme.
  https://butterfly-monitoring.net
package: "Indicia form customisations"
type: module
version: 1.0.0
core_version_requirement: ^9.4 || ^10
dependencies:
 - iform_custom_forms:iform_custom_forms
```

Each project will appear as a separate module in the Drupal admin interface
with the name and description you provide. You should use the version number
to track your changes.

To use the custom code for your project you will want to enable your module.

There might be customisations relevant to several projects. For example, there 
may be several butterfly monitoring projects which use similar methods. You 
could create a `butterfly_monitoring` project and enable that in addition to 
the module which is specific to your project.

See the documentation about 
[Customising pages built using prebuilt forms in Drupal](https://indicia-docs.readthedocs.io/en/latest/site-building/iform/customising-page-functionality.html)
and the 
[Tutorial: writing a prebuilt form](https://indicia-docs.readthedocs.io/en/latest/developing/client-website/tutorial-writing-drupal-prebuilt-form/index.html)

With the advent of this module, we have a new place to store our files
rather than those mentioned in the documentation as it stands currently (April
2023).

When writing prebuilt forms, where previously you would include files like
```
require_once 'includes/map.php';
```
now you must allow for the relocation by writing
```
$helperPath = realpath(iform_client_helpers_path());
require_once "$helperPath/prebuilt_forms/includes/map.php";
```