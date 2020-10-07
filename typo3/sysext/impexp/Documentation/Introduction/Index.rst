.. include:: ../Includes.txt


.. _introduction:

============
Introduction
============

What does it do?
================

The goal of the core extension "impexp" is to provide a simple but powerful
interface for exporting and importing pages and records of a TYPO3 instance.

Export
======

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
