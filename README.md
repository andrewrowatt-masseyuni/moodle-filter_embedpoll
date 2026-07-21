# Embed poll #

A text filter that turns a simple embed code into an interactive, anonymous
single-question poll, anywhere filtered text is displayed — labels, pages,
book chapters and so on.

Writing the following in any content area:

```
{poll:"Red","Green","Blue"}
```

renders a poll widget with one button per option. Students vote (and may
change their vote later); results are revealed to a student only after they
have voted, while teachers and managers always see the results. Voting is
handled over AJAX, so the page does not reload.

## Features ##

* Embed a poll with a one-line code — no activity to configure.
* Results are hidden from students until they cast a vote, and vote tallies
  are never sent to the browser before then.
* Students can change their vote; one vote per user per poll.
* Teachers and managers see results immediately without voting
  (`filter/embedpoll:viewresults`).
* Works in book chapters, with each chapter keeping its own polls.
* Poll and vote data are cleaned up automatically when the enclosing
  activity or course is deleted.
* Implements the Privacy API (GDPR): user votes are exported and deleted on
  request.

## Usage ##

Add an embed code to any content that passes through filters:

```
{poll:"Option one","Option two","Option three"}
```

* Options are double-quoted and separated by commas.
* A poll needs at least two options; otherwise the text is left unchanged.
* The same code can be used more than once on a page — each occurrence is a
  separate poll.

Note that a poll is identified by its location and its exact list of
options. Editing the option labels of an existing poll therefore creates a
new poll, and votes cast on the old wording are no longer shown.

## Capabilities ##

| Capability | Default roles | Purpose |
| ---------- | ------------- | ------- |
| `filter/embedpoll:vote` | Student | Cast or change a vote in a poll |
| `filter/embedpoll:viewresults` | Teacher, Non-editing teacher, Manager | Always see poll results without voting |

## Requirements ##

* Moodle 4.5 (2024100700) or later.

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to
   add extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/filter/embedpoll

Afterwards, log in to your Moodle site as an admin and go to _Site
administration > Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

After installing, enable the filter at _Site administration > Plugins >
Filters > Manage filters_ by setting **Embed poll** to "On".

## License ##

2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
details.

You should have received a copy of the GNU General Public License along with
this program. If not, see <https://www.gnu.org/licenses/>.
