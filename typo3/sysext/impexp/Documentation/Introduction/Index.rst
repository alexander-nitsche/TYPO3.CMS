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
   --> "Tab 'Preset'"

   if "Load preset?" then
      -->[yes] "Load preset"

      if "Loading successful?" then
         -->[yes] "Click 'Next'" as clickNextOnPreset
      else
         -->[no] "Tab 'Preset'"
      endif
   else
      -->[no] clickNextOnPreset
   endif

   --> "Tab 'Page Tree'"
   --> "Configure database export"
   --> "Apply" as applyPageTree

   if "Is configuration done?" then
      -->[yes] "Click 'Next'" as clickNextOnPageTree
   else
      -->[no] "Configure database export"
   endif

   clickNextOnPageTree --> "Tab 'Files'"
   --> "Configure files export"
   --> "Apply" as applyFiles

   if "Is configuration done?" then
      -->[yes] "Click 'Next'" as clickNextOnFiles
   else
      -->[no] "Configure files export"
   endif

   clickNextOnFiles --> "Tab 'Meta Data'"

   if "Is preset loaded?" then
      -->[yes] "Update meta data"
      --> "Click 'Next'" as clickNextOnMetaData
   else
      -->[no] "Insert meta data"
      --> clickNextOnMetaData
   endif

   clickNextOnMetaData --> "Tab 'Export'"

   if "Save configuration as preset?" then
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
