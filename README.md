## Beacon (Notifications)

[![License: GPL v3](https://img.shields.io/badge/License-GPL_v3-blue.svg?style=flat-square)](https://www.gnu.org/licenses/old-licenses/gpl-3.0.en.html) [![MediaWiki: >=1.41](https://img.shields.io/badge/MediaWiki-%3E%3D1.47-%2336c?style=flat-square&logo=Wikipedia)](https://www.mediawiki.org)

**Beacon** is a MediaWiki extension providing notification capabilities; it is being built to replace Echo on Telepedia, and is available for other wiki farms that are not configured using CentralAuth.

### Why?
Echo is not really a great extension for notification capabilities for many different reasons:
1. If you do not define all of your wikis up front in `$wgConf`, then there is no way for Echo to know the URL of the wiki, or the sitename etc. unless you hack around this and lazy-load it (trivially done).
2. Echo only stores the notification count for each wiki in the shared database (for some bizarre reason), whilst the notifications themselves are stored in each wikis database. The code is purposefully written to fail if the SessionProvider is not CentralAuth as the token is used to verify the user across wikis.
3. Notifications and "alerts" are separated which has always been an odd way to do things.

In respect of #2, wikis using `$wgSharedDB` do not require any funky authentication, since their `actor` and `user` IDs are persistent across wikis. We consider that it is easier to just write something fresh rather than mess about forking Echo etc.

In contrast, Beacon is built with immutability first – a notification is created once, at dispatch time, with all of the required information to display it to any wiki on a farm. This drops a lot of Echo's fluff where the message of a notification can be changed after the notification has already been dispatched. However, this comes at the obvious cost of immutability (ie if the sitename of a wiki is changed after a notification has been dispatched, it will obviously be wrong when the user views the notification.)

This is an acceptable trade-off for us as these things do not change frequently; however we will explore methods to update these things when these things do happen.

### Installation

### Contributing

### Licensing
