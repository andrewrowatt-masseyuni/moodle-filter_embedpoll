# Embed poll filter (filter_embedpoll)

Part of **embedded polls**. The design for all three plugins is documented in
`local/embedpoll/README.md`.

This filter replaces each poll token in formatted text with the poll itself. Teachers add polls
with the **Poll** button in the editor (`tiny_embedpoll`), which puts a token like this into the
text:

```html
<div class="embedpoll-embed" data-embedpoll="0123456789abcdef"></div>
```

The poll's options and votes are stored by `local_embedpoll`, which this filter depends on.

* In an activity's text, a token resolves to that activity's poll.
* In a course's text (a section summary), it resolves to one of the course's section polls.
* Anywhere else, a token renders as nothing.

A token whose poll belongs somewhere else shows a notice to those who may manage polls, and
nothing to anyone else.

## Requirements

Requires `local_embedpoll`. Moodle 4.5 only.

## License

2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU
General Public License as published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not,
see <https://www.gnu.org/licenses/>.
