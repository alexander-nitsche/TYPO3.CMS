.. include:: ../Includes.txt


.. _introduction:

============
Introduction
============

What does it do?
================

The goal of the core extension "impexp" is to provide a simple but powerful
interface for exporting and importing pages and records of a TYPO3 instance.

Export module
=============

.. uml::

   "Tab <i>Preset</i>" as tabPreset -> tabPreset: Load preset\n(optional)
   tabPreset -> "Tab <i>Page Tree</i>" as tabPageTree: Click Next
   tabPageTree --> tabPreset: Click Prev

   tabPageTree -> tabPageTree: Configure\ndatabase export\n& apply
   tabPageTree -> "Tab <i>Files</i>" as tabFiles: Click Next
   tabFiles --> tabPageTree: Click Prev

   tabFiles -> tabFiles: Configure\nfiles export\n& apply
   tabFiles -> "Tab <i>Meta Data</i>" as tabMetaData: Click Next
   tabMetaData --> tabFiles: Click Prev

   tabMetaData -> tabMetaData: Insert / update\nmeta data
   tabMetaData -> "Tab <i>Export</i>" as tabExport: Click Next
   tabExport --> tabMetaData: Click Prev

   tabExport -> tabExport: Insert / update\npreset title\n& save preset\n(optional)
   tabExport -> tabExport: Configure export file
   tabExport -> tabExport: Save export to server
   tabExport -> tabExport: Download export

Import module
=============

.. uml::

   "Tab <i>Preset</i>" as tabPreset -> tabPreset: Load preset\n(optional)
   tabPreset -> "Tab <i>Load</i>" as tabLoad: Click Next
   tabLoad --> tabPreset: Click Prev

   tabLoad -> tabLoad: Upload import\nto server\n(optional)
   tabLoad -> tabLoad: Select import\non server\n& load
   tabLoad -> "Tab <i>Page Tree</i>" as tabPageTree: Click Next
   tabPageTree --> tabLoad: Click Prev

   tabPageTree -> tabPageTree: Configure\ndatabase import\n& apply
   tabPageTree -> "Tab <i>Meta Data</i>" as tabMetaData: Click Next
   tabMetaData --> tabPageTree: Click Prev

   tabMetaData -> tabMetaData: Insert / update\nmeta data
   tabMetaData -> "Tab <i>Import</i>" as tabImport: Click Next
   tabImport --> tabMetaData: Click Prev

   tabImport -> tabImport: Insert / update\npreset title\n& save preset\n(optional)
   tabImport -> tabImport: Import

Concept
=======

Entity "Export Preset"
----------------------

- Fully-Qualified TCA
   - display records in Web > List module
   - make all fields editable
   - fields:
      - [ctrl] hideTable = false;
      - [ctrl] rootLevel = -1;
      - uid
      - pid
      - title
      - description => add
      - user_uid => rename to cruser_id
      - public => remove
      - item_uid => remove
      - preset_data => rename to configuration
      - tstamp
      - crdate
      - hidden => add
      - deleted => add
- CRUD
   - perform database actions by DataHandler
   - sync handling of TCEforms and export module: Soft / hard deletion, record history, access management
   - Creation:
      - create export preset if
         - export preset by this backend user + pid + title does not exist
         - export module accessible by backend user
         - record creation allowed to backend user on this pid
      - pid = root page of exported page subtree
      - title + description = form fields in export module tab "Meta Data"
      - cruser_id = backend user using export module
      - configuration = serialized export configuration
      - tstamp + crdate = creation timestamp
      - hidden = false
      - deleted = false
   - Read:
      - show export preset if
         - pid = current root page of export module
         - export module accessible by backend user
         - page / record reading allowed to backend user on this pid
   - Update:
      - update export preset if
         - export preset by this backend user + pid + title does exist
         - export module accessible by backend user
         - record editing allowed to backend user on this pid
      - description = form field in export module tab "Meta Data"
      - configuration = serialized export configuration
      - tstamp = update timestamp
   - Deletion:
      - delete export preset if
         - export module accessible by backend user
         - record deletion allowed to backend user on this pid
      - deleted = true

Entity "Import Preset"
----------------------

- basic setting as entity "Export Preset" but used for import configurations

Documentation
-------------

- create full extension docs at /Documentation
- add good explanations for all 3 export configuration fields:
   - "Include tables"
   - "Include relations to tables"
   - "Use static relations for tables"
      => what happens if selected / is missing / which scenario is it good for?

UI
--

- Export module
   - tab "Preset"
      - list all existing presets of page with
         - normal TCA list item
         - action: load (jump to tab "Page Tree" with preset loaded + message)
      - list all existing presets of subtree with
         - grouped by page ID (page title)
            - normal TCA list item
            - action: load (jump to page in page tree + tab "Page Tree" with preset loaded + message)
      - Button "Next"
   - tab "Page Tree"
      - Page (Name+ID) (readonly, change by clicking in pagetree submodule)
      - Tree
      - Levels
      - Include tables
      - Include relations to tables => Include related data of foreign tables
      - Use static relations for tables => Include keys only of foreign tables
      - Show static relations => Show foreign keys in export preview
      - Exclude elements: Add same row as for "Preview export": Include [check] [symbol] [title/table+id if no title]
      - Exclude disabled elements (is it really required or does it add complexity?)
      - Button "Apply" (former "Update", maybe with loading overlay, maybe with enabled and disabled state)
      - Button "Prev" + Button "Next" (maybe with titles of prev + next tabs)
      - Preview export (single records removable)
   - tab "Files"
      - Exclude media which is linked in HTML / CSS files
      - Button "Apply" (maybe with loading overlay, maybe with enabled and disabled state)
      - Button "Prev" + Button "Next" (maybe with titles of prev + next tabs)
      - Preview export (single records removable?)
   - tab "Meta Data"
      - info: this form used for preset and exported file
      - title
      - description
      - access: public (maybe remove from here completely and add hint somewhere that access can be changed in view "Presets")
      - Button "Prev" + Button "Next" (maybe with titles of prev + next tabs)
      - Preview export
   - tab "Export"
      - File format
      - Section "Save configuration as preset"
         - input field for preset title (if empty, suggest converted title)
         - button "Save preset"
            => on save: "Configuration saved to path/file successfully."
      - Section "Save export to server"
         - Readonly server path + input field for filename (if empty, suggest converted title)
         - Checkbox "Override existing file" (empty by default)
         - Button "Save export to server"
            => on save: "Configuration saved to path/file successfully."
      - Section "Download export"
         - Button "Download export"
      - Button "Prev" (maybe with titles of prev + next tabs)
      - Preview export

- Import module
   - tab "Preset"
      - list all existing presets of page with
         - normal TCA list item
         - action: load (jump to tab "Load" with preset loaded + message)
      - list all existing presets of subtree with
         - grouped by page ID (page title)
            - normal TCA list item
            - action: load (jump to page in page tree + tab "Page Tree" with preset loaded + message)
      - Button "Next"
   - tab "Load"
      - Select file to import
      - Upload file from local computer
         - Overwrite existing files
      - Button "Load" (former "Update", maybe with loading overlay, maybe with enabled and disabled state)
      - Button "Next" (maybe with title of next tab)
   - tab "Page Tree"
      - Levels
      - Update records => Insert [ ] Update [x] (with info)
      - Do not show differences in records => Why, for performance reasons?
      - Force ALL UIDs values => Force UIDs of import file
      - Write individual DB actions during import to the log => Log all database actions => Log Level: [ ] normal [ ] high
      - Include tables (as in view "Export")
      - Exclude elements: Add same row as for "Preview import": Include [check] [symbol] [title/table+id if no title]
      - Button "Apply" (maybe with loading overlay, maybe with enabled and disabled state)
      - Button "Prev" + Button "Next" (maybe with titles of prev + next tabs)
      - Preview import (single records removable)
   - tab "Meta Data"
      - info: this form used for preset
      - title
      - description
      - access: public (maybe remove from here completely and add hint somewhere that access can be changed in view "Presets")
      - Button "Prev" + Button "Next" (maybe with titles of prev + next tabs)
      - Preview import
   - tab "Import"
      - Section "Save configuration as preset"
         - input field for preset title (if empty, suggest converted title)
         - button "Save preset"
            => on save: "Configuration saved to path/file successfully."
      - Section "Import"
         - Button "Import"
      - Button "Prev" (maybe with title of prev tab)
      - Preview import

- General: Sort lists alphabetically, if no other sorting is more appropriate.
- General: Add info overlays with detailed explanations for all fields that are not super obvious.
- General: Always return to the current tab when submitting a form.
- General: Are there UI elements available for progress / proceeding with a multistep form?

CLI
---

- Add CLI export command (https://forge.typo3.org/issues/84718)
   - params
      - file (required) (The path and filename to export to (.t3d or .xml))
      - preset (optional) (The preset to load for export)
      - pid (optional) (The pid indicates root page of export)
      - depth (optional) (Traversal depth, same as levels in export module) (see typo3/sysext/lowlevel/Classes/Command/DeletedRecordsCommand.php)
      - includeTable (optional) (multiple) (if added, only records of these tables will be exported, same as in export module)
      - includeRelated (optional) (if added, records of these tables will be exported only if linked by another record, same as in export module)
      - keepForeignKey (optional) (if added, foreign keys will be kept in records of these tables, same as in export module)
      - enableLog (optional)
      - excludeUid (optional) (if added, this particular record (table:uid) will be exluded from export, same as in export module)
   - constraint: preset _xor_ pageId && includeTable && includeRelated && keepForeignKey
- Update CLI import command
   - params
      - preset (optional) (The preset to load for export)
      - pageId -> pid (optional) (see typo3/sysext/lowlevel/Classes/Command/DeletedRecordsCommand.php)
      - depth (optional) (Traversal depth, same as levels in import module) (see typo3/sysext/lowlevel/Classes/Command/DeletedRecordsCommand.php)
      - includeTable (optional) (multiple) (if added, only records of these tables will be imported, same as in import module)
      - excludeUid (optional) (if added, this particular record (table:uid) will be exluded from import, same as in import module)
   - constraint: preset _xor_ pageId && includeTable
- General: Add shortcuts for each param
- General: Add summary log with statistics of import/export

Misc
----

- Check and maybe adjust indentation of entries in section "Preview import" / "Preview export"

TODOS
=====

- Alex: What is the difference between pages and records export (always item_uid=0)?
- Alex: check https://forge.typo3.org/issues/85430 for any missing issue
- Alex: check and use official wording of "page tree" and "files" in backend user access tabs
- Alex: find existing GUI elements and suggest / make scribble
- Alex: Make all module configuration fields available as CLI command params
- Alex: Make creation of presets available to CLI: Sync structure (serialized <-> YAML/XML/JSON) and storage (database <-> file)?
- Discussion: Export Preset: how to best visualize field "configuration"
  (if visualization for XML or JSON or YAML available, maybe switching from serialized object to that format is recommended?)
- Discussion: Export Preset: how to best replace access field "public" by official TYPO3 Core access management
- Discussion: Add CLI param "dry-run" for collecting stats on imports and exports?
