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
   tabFiles -> "Tab <i>Export</i>" as tabExport: Click Next
   tabExport --> tabFiles: Click Prev

   tabExport -> tabExport: Configure preset\n& save preset\n(optional)
   tabExport -> tabExport: Configure export file
   tabExport -> tabExport: Save export on server
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
   tabPageTree -> "Tab <i>Import</i>" as tabImport: Click Next
   tabImport --> tabPageTree: Click Prev

   tabImport -> tabImport: Configure preset\n& save preset\n(optional)
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
      - identifier (new)
      - description (new)
      - configuration (former: preset_data)
      - cruser_id (former: user_uid)
      - tstamp
      - crdate
      - hidden (new)
      - deleted (new)
   - remove fields:
      - public (replace by native TYPO3 access management)
      - item_uid (replace by pid)

- CRUD
   - Creation:
      - create export preset if
         - export preset by this backend user + pid + title does not exist
         - export module accessible by backend user
         - record creation allowed to backend user on this pid
      - pid = root page of exported page subtree
      - title + description = form fields in export module tab "Meta Data"
      - identifier = auto-generated from backend user + pid + title (unique, used for CLI commands)
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

- General: Sync handling of TCEforms and import/export module:
   - soft / hard deletion
   - record history
   - access management

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
         - action: load (jump to tab "Page Tree" with preset loaded + success message)
      - list all existing presets of subtree with
         - grouped by page ID (page title)
            - normal TCA list item
            - action: load (jump to page in page tree + tab "Page Tree" with preset loaded + success message + info about page tree jump)
      - Button "Next"
   - tab "Page Tree"
      - Page ID
      - Page Tree (former: Tree)
      - Depth (former: Levels)
      - Include tables
      - Include relations to tables (find better wording)
      - Use static relations for tables (find better wording)
      - Show static relations (find better wording)
      - Exclude elements: Add same row as for "Preview export": Include [check] [symbol] [title/table+id if no title]
      - Exclude disabled elements (is it really required or does it add complexity? => yes, as it is about records which are disabled by TCA configuration, not about being disabled by this export form ..)
      - Button "Apply" (former: "Update")
      - Button "Prev" + Button "Next"
      - Preview export (single records removable)
   - tab "Files"
      - Exclude media which is linked in HTML / CSS files
      - Button "Apply"
      - Button "Prev" + Button "Next"
      - Preview export (single records removable?)
   - tab "Export"
      - Section "Save configuration as preset"
         - title
         - description
         - button "Save preset"
            => on save: "Configuration saved to path/file successfully."
      - Section "Save export"
         - File format
         - Subsection "Save export on server"
            - Readonly server path + input field for filename (if empty, suggest something)
            - Button "Save export on server"
               => on save: "Export saved to path/file successfully."
         - Subsection "Download export"
            - Button "Download export"
      - Button "Prev"
      - Preview export

- Import module
   - tab "Preset"
      - list all existing presets of page with
         - normal TCA list item
         - action: load (jump to tab "Load" with preset loaded + success message)
      - list all existing presets of subtree with
         - grouped by page ID (page title)
            - normal TCA list item
            - action: load (jump to page in page tree + tab "Page Tree" with preset loaded + success message)
      - Button "Next"
   - tab "Load"
      - Select file to import
      - Upload file from local computer
         - Overwrite existing files
      - Button "Load" (former: "Update")
      - Button "Next"
   - tab "Page Tree"
      - Page ID
      - Page Tree (former: Tree)
      - Depth (former: Levels)
      - Include tables (as in view "Export")
      - Method: Insert records / Update records / Update records but ignore the pid (former: Update records)
      - Do not show differences in records => Why, for performance reasons?
      - Force uids of import file (former: Force ALL UIDs values)
      - Log all database actions (former: Write individual DB actions during import to the log)
      - Exclude elements: Add same row as for "Preview import": Include [check] [symbol] [title/table+id if no title]
      - Button "Apply"
      - Button "Prev" + Button "Next"
      - Preview import (single records removable)
   - tab "Import"
      - Section "Save configuration as preset"
         - title
         - description
         - button "Save preset"
            => on save: "Configuration saved to path/file successfully."
      - Section "Import"
         - Button "Import"
      - Button "Prev"
      - Preview import

- General: perform database actions by DataHandler
- General: Sort lists alphabetically, if no other sorting is more appropriate.
- General: Add info overlays with detailed explanations for all fields that are not super obvious.
- General: Always return to the current tab when submitting a form.
- General: Are there UI elements available for progress / proceeding with a multistep form?
- General: Add more apply buttons to UI, maybe after each configuration section
- General: Apply button maybe with loading overlay, maybe with enabled and disabled state (depending on change in formular?)
- General: Prev and next buttons maybe contain titles of prev + next tabs

CLI
---

- Export command (https://forge.typo3.org/issues/84718)
   - params
      - filepath (required) (The path and filename to export to)
      - filetype (required) (t3d/t3dz/xml)
      - preset (optional) (The preset identifier to use for export)
      - pid (optional) (The pid indicates root page of export)
      - depth (optional) (Traversal depth, same as levels in export module)
      - includeTable (optional, multiple) (if added, only records of these tables will be exported, same as in export module)
      - includeRelated (optional, multiple) (if added, records of these tables will be exported only if linked by another record, same as in export module)
      - includeStatic (optional, multiple) (if added, foreign keys will be kept in records of these tables, same as in export module)
      - excludeRecord (optional, multiple) (if added, this particular record (table:uid) will be exluded from export, same as in export module)
      - includeRecord (optional, multiple) (if added, this particular record (table:uid) will be included into export, same as in export module)
      - dry-run (optional) (if added, the command returns all queries instead of executing them, same as "Export Preview" in export module)
   - preset and other params can be used together: they get merged in a sensible and well documented way

- Import command
   - params
      - file -> filepath (filetype gets auto-detected)
      - preset (optional) (The preset identifier to use for import)
      - pid (optional)
      - depth (optional) (Traversal depth, same as levels in import module)
      - includeTable (optional, multiple) (if added, only records of these tables will be imported, same as in import module)
      - excludeRecord (optional, multiple) (if added, this particular record (table:uid) will be exluded from import, same as in import module)
      - method [insert(default)/update/updateButIgnorePid] (optional) (former: updateRecords, ignorePid)
      - forceUid (optional) (if added, uids of imported records will be forced)
      - enableLog (optional) (if added, database queries get logged)
      - dry-run (optional) (if added, the command returns all queries instead of executing them, same as "Import Preview" in import module)
   - preset and other params can be used together: they get merged in a sensible and well documented way

- General: Add shortcuts for each param
- General: Add summary log with configuration and statistics of import/export
- General: Make all module configuration fields available as CLI command params (such that an integrator can fully switch from TYPO3 backend to CLI for large TYPO3 instances which exceed PHP maximum execution time)

Misc
----

- Check and maybe adjust indentation of entries in section "Preview import" / "Preview export"
- General: Use as many core functionality as possible
- General: Refactor to smaller yet better testable portions of code (e.g. small controllers)

TODOS
=====

- Alex: What is the difference between pages and records export (always item_uid=0)?

.. code-block:: json
   :caption: Export pages

   {
      "pagetree": {
         "id": "",
         "tables": ""
      },
      "record": [
         "fe_users:1"
      ],
      "external_ref": {
         "tables": [
            "_ALL"
         ]
      },
      "external_static": {
         "tables": [
            "fe_groups"
         ]
      },
      "showStaticRelations": "1",
      "excludeDisabled": "1",
      "preset": {
         "title": "Record Export",
         "public": 0
      },
      "meta": {
         "title": "",
         "description": "",
         "notes": ""
      },
      "filetype": "xml",
      "filename": "",
      "excludeHTMLfileResources": "",
      "saveFilesOutsideExportFile": "",
      "extension_dep": "",
      "exclude": []
   }

.. code-block:: json
   :caption: Export records

   {
      "pagetree": {
         "id": "0",
         "levels": "0",
         "tables": [
            "_ALL"
         ]
      },
      "external_ref": {
         "tables": ""
      },
      "external_static": {
         "tables": [
            "fe_groups"
         ]
      },
      "showStaticRelations": "",
      "excludeDisabled": "1",
      "..": ".."
   }

- Link: https://forge.typo3.org/issues/85430
- Alex: find existing GUI elements and suggest / make scribble
- Discussion: Merge tabs "Page Tree" and "Files" into "Configuration" to reduce tabbing?
- Discussion: Import / Export: File formats of preset and dump files still state of the art?
   - How to best visualize TCA field "configuration"? If visualization for XML or JSON or YAML or FlexForm available, maybe switching from serialized object to that format is preferred?
   - Is there any advantage of T3D over XML? If not, could we skip support?
   - Is there any advantage of XML over JSON? Maybe having a scheme? If not, should we switch to the slim JSON format?
- Discussion: Import / Export Preset: How to best support a user-friendly copy & paste / instant preset, e.g. User A -> User B: "Please export your TYPO3 instance with this preset snippet and paste in here the export .."
- Discussion: Export Preset: How to best replace access field "public" by official TYPO3 Core access management
- Discussion: Wordings:
   - levels -> depth (see e.g. DeletedRecordsCommand)
   - pageId -> pid (see e.g. DeletedRecordsCommand)
   - No tree exported - only tables on the page. -> No pages exported - only records on this page.
