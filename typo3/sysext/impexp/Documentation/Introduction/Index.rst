.. include:: ../Includes.txt


.. _introduction:

============
Introduction
============

What does it do?
================

The goal of the core extension "impexp" is to provide a simple but powerful
interface for exporting and importing pages and records of a TYPO3 instance.

Export module - Sequence Diagram
================================

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

Export module - Activity Diagram
================================

.. uml::

   (*) --> "Export module"

   partition "Tab <i>Preset</i>" {

      if "Load preset?" as loadPreset then
         -->[yes] "Load preset"

         if "Loading successful?" then
            -->[yes] "Click 'Next'" as clickNextOnPreset
         else
            -->[no] loadPreset
         endif
      else
         -->[no] clickNextOnPreset
      endif

   }

   partition "Tab <i>Page Tree</i>" {

      --> "Configure database export"
      --> "Apply" as applyPageTree

      if "Is configuration done?" then
         -->[yes] "Click 'Next'" as clickNextOnPageTree
      else
         -->[no] "Configure database export"
      endif

   }

   partition "Tab <i>Files</i>" {

      clickNextOnPageTree --> "Configure files export"
      --> "Apply" as applyFiles

      if "Is configuration done?" then
         -->[yes] "Click 'Next'" as clickNextOnFiles
      else
         -->[no] "Configure files export"
      endif

   }

   partition "Tab <i>Meta Data</i>" {

      clickNextOnFiles --> if "Is preset loaded?" then
         -->[yes] "Update meta data"
         --> "Click 'Next'" as clickNextOnMetaData
      else
         -->[no] "Insert meta data"
         --> clickNextOnMetaData
      endif

   }

   partition "Tab <i>Export</i>" {

      clickNextOnMetaData --> if "Save configuration as preset?" then
         -->[yes] if "Is preset loaded?" then
            -->[yes] "Update preset title"
            --> "Click 'Save preset'" as savePreset
            --> "Configure export file" as configureExport
         else
            -->[no] "Insert preset title"
            --> savePreset
         endif
      else
         -->[no] configureExport
      endif

      configureExport --> "Save export to server"
      --> (*)

      configureExport --> "Download export"
      --> (*)

   }
