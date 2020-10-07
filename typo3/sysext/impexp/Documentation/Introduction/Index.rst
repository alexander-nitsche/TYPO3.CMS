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

   "Tab <i>Load</i>" as tabLoad -> tabLoad: Upload file from\nlocal computer\n(optional)
   tabLoad -> tabLoad: Select file\nto import\n& click Load
   tabLoad -> "Tab <i>Page Tree</i>" as tabPageTree: Click Next
   tabPageTree --> tabLoad: Click Prev

   tabPageTree -> tabPageTree: Configure\ndatabase import\n& apply
   tabPageTree -> "Tab <i>Import</i>" as tabImport: Click Next
   tabImport --> tabPageTree: Click Prev

   tabImport -> tabImport: Import
