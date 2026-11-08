## Beacon (Notifications)

[![License: GPL v3](https://img.shields.io/badge/License-GPL_v3-blue.svg?style=flat-square)](https://www.gnu.org/licenses/old-licenses/gpl-3.0.en.html) [![MediaWiki: >=1.41](https://img.shields.io/badge/MediaWiki-%3E%3D1.47-%2336c?style=flat-square&logo=Wikipedia)](https://www.mediawiki.org)

**Beacon** is a MediaWiki extension providing notification capabilities; it is being built to replace Echo on Telepedia, and is available for other wiki farms that are not configured using CentralAuth.

### Why?
Echo is not really a great extension for notification capabilities for many different reasons:
1. If you do not define all of your wikis up front in `$wgConf`, then there is no way for Echo to know the URL of the wiki, or the sitename etc. unless you hack around this and lazy-load it (trivially done).
2. Echo only stores the notification count for each wiki in the shared database (for some bizarre reason), whilst the notifications themselves are stored in each wikis database. The code is purposefully written to fail if the SessionProvider is not CentralAuth as the token is used to verify the user across wikis.
3. Notifications and "alerts" are separated which has always been an odd way to do things.

In respect of #2, wikis using `$wgSharedDB` do not require any funky authentication, since their `actor` and `user` IDs are persistent across wikis. We consider that it is easier to just write something fresh rather than mess about forking Echo etc.

Echo also has the odd behaviour that notification messages, icons, etc. can be changed after the notification has been dispatched, which is a bit weird. In contrast, Beacon is built with immutability first – a notification is created once, at dispatch time, with all of the required information to display it to any wiki on a farm. This drops a lot of Echo's fluff where the message of a notification can be changed after the notification has already been dispatched. However, this comes at the obvious cost of immutability (ie if the sitename of a wiki is changed after a notification has been dispatched, it will obviously be wrong when the user views the notification.)

This is an acceptable trade-off for us as these things do not change frequently; however we will explore methods to update these things when these things do happen.

### Installation

### Icons
Like Echo, every notification dispatched by Beacon can support an icon. Unlike Echo, however, icons (like the rest of Beacon) are immutable and are set when the notification is dispatched from the sending wiki. This ensures that the icon displays the same on every wiki, even for notifications which were created by an extension that the wiki viewing the notification on does not have installed.

#### Codex icons
The simplest option is to use a [Codex icon](https://doc.wikimedia.org/codex/latest/icons/all-icons.html). This can be done by returning the name of the icon verbatim from the presenter:

```php
return new RenderedNotification(
	// [...]
	icon: 'cdxIconUndo',
);
```

Right-to-left languages are handled automatically and the icon is flipped if needed. For Beacon to display the Codex icon, it must be listed in `resources/ext.beacon.popover/icons.less`. We have selected the most common ones, but more can be added if necessary. Any other icon will fallback to the bell icon.

#### Custom Icons
If there is no icon for what you need in Codex, you can provide your own via a ResourceLoader image module which must be registered with Beacon via the `attributes` definition in `extension.json`:

```json
{
	"ResourceModules": {
		"ext.someExtension.icons": {
			"class": "MediaWiki\\ResourceLoader\\ImageModule",
			"selector": ".some-extension-icon-{name}",
			"images": {
				"thanks": {
					"file": {
						"ltr": "thanks-ltr.svg",
						"rtl": "thanks-rtl.svg"
					}
				}
			}
		}
	},
	"attributes": {
		"Beacon": {
			"Icons": {
				"some-extension-icon-thanks": {
					"module": "ext.someExtension.icons",
					"image": "thanks"
				}
			}
		}
	}
}
```

Then return the name you registered in the presenter (`icon: 'some-extension-icon-thanks'`). Note that if you provide your own icon, you **must** provide both the LTR and RTL variant of the icon as Beacon will not flip the icon – ResourceLoader will, however, determine which variant to display based on the language of the viewer.



### Contributing

### Licensing
