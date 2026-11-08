<template>
	<li
		class="beacon-item"
		:class="{ 'beacon-item--unread': isUnread }"
	>
		<span class="beacon-item__icon">
			<span class="beacon-icon" :class="iconClass" :style="iconStyle"></span>
		</span>
		<span class="beacon-item__text">
			<a
				class="beacon-item__header"
				:href="notification.url"
				@click="onOpen"
				@auxclick="onOpen"
			>
				{{ notification.header }}
			</a>
			<span v-if="notification.body" class="beacon-item__body">
				{{ notification.body }}
			</span>
			<span class="beacon-item__footer">
				<span class="beacon-item__links">
					<span v-if="isForeign" class="beacon-item__wiki">{{ notification.wiki.name }}</span>
					<a
						v-for="link in notification.links"
						:key="link.url"
						class="beacon-item__link"
						:href="link.url"
						@click="onOpen"
						@auxclick="onOpen"
					>{{ link.label }}</a>
				</span>
				<time
					class="beacon-item__time"
					:datetime="notification.created"
					:title="absoluteTime"
				>{{ relativeTime }}</time>
			</span>
		</span>
		<button
			type="button"
			class="beacon-item__dot"
			:title="toggleLabel"
			:aria-label="toggleLabel"
			@click="$emit( isUnread ? 'mark-read' : 'mark-unread', notification.id )"
		></button>
	</li>
</template>

<script>
const { defineComponent, computed } = require( 'vue' );
const DateFormatter = require( 'mediawiki.DateFormatter' );

/**
 * We draw the custom icons (which come from the sending wikis load.php) as mask icons
 */
function maskStyle( icon ) {
	if ( !/^https?:\/\//.test( icon ) ) {
		return null;
	}
	const value = `url("${ icon.replace( /["\\\n]/g, encodeURIComponent ) }")`;
	return { maskImage: value, WebkitMaskImage: value };
}

module.exports = defineComponent( {
	name: 'NotificationItem',
	props: {
		notification: { type: Object, required: true }
	},
	emits: [ 'mark-read', 'mark-unread' ],
	setup( props, { emit } ) {
		const created = computed( () => new Date( props.notification.created ) );
		const isUnread = computed( () => props.notification.read === null );

		/**
		 * Mark a notification as read once it has been clicked
		 * @param {MouseEvent} e
		 */
		function onOpen( e ) {
			if ( e.type === 'auxclick' && e.button !== 1 ) {
				return;
			}
			if ( isUnread.value ) {
				emit( 'mark-read', props.notification.id );
			}
		}

		return {
			isUnread,
			onOpen,
			toggleLabel: computed( () => mw.msg( isUnread.value ? 'beacon-popover-mark-read' : 'beacon-popover-mark-unread' ) ),
			// we only display the wiki name if the wiki is a foreign wiki
			isForeign: computed( () => props.notification.wiki.id !== mw.config.get( 'wgDBname' ) ),
			iconClass: computed( () => /^cdxIcon\w+$/.test( props.notification.icon ) ? `beacon-icon--${ props.notification.icon }` : null ),
			iconStyle: computed( () => maskStyle( props.notification.icon ) ),
			relativeTime: computed( () => DateFormatter.formatRelativeTimeOrDate( created.value ) ),
			absoluteTime: computed( () => DateFormatter.formatTimeAndDate( created.value ) )
		};
	}
} );
</script>
