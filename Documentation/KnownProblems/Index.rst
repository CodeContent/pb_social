.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _known-problems:

Known Problems
==============

If you are testing the extension locally, you may encounter some small problems:

- Facebook posts may not be loaded because of some xampp malconfiguration. You have two choices here:

    - Update your ssl certificates (.cert files) or Turn off ssl verification (considered as unsafe method because you'll send your credentials unencrypted) - See FAQ for details
    - Private Facebook profile posts may be not displayed.

- Posts without image may not load the placeholder image because of a 'Not allowed to load local resource' error.

- Depending on your Instagram developer app status, you may not be allowed to get data from other users. See the FAQ section for more information.

- Clearing the system cache, will also clear all posts. Run the scheduler task manually afterwards to avoid the empty page.

- Scheduler Task should only run each 10-15 minutes due to API restrictions.