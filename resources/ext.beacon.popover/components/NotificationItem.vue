<template>
	<li
		class="beacon-card__item beacon-item"
		:class="{ 'beacon-item--unread': isUnread }"
	>
		<span class="beacon-card__icon beacon-item__icon">
			<span class="beacon-icon" :class="iconClass" :style="iconStyle"></span>
		</span>
		<span class="beacon-card__text">
			<a class="beacon-item__header" :href="notification.url">
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
					>{{ link.label }}</a>
				</span>
				<time
					class="beacon-item__time"
					:datetime="notification.created"
					:title="absoluteTime"
				>{{ relativeTime }}</time>
			</span>
		</span>
		<span
			v-if="isUnread"
			class="beacon-item__dot"
			:title="$i18n( 'beacon-popover-unread' ).text()"
		></span>
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
	setup( props ) {
		const created = computed( () => new Date( props.notification.created ) );

		return {
			isUnread: computed( () => props.notification.read === null ),
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

<style lang="less">
@import 'mediawiki.skin.variables.less';

.beacon-item {
	position: relative;
	font-size: @font-size-small;
	line-height: @line-height-xx-small;

	&:hover {
		background-color: @background-color-interactive-subtle--hover;
	}

	.beacon-card__text {
		gap: @spacing-12;
	}

	&__icon {
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: @border-radius-circle;
		background-color: @background-color-neutral;
		color: @color-subtle;
	}


	&__header {
		&,
		&:visited {
			color: @color-base;
		}

		text-decoration: none;
		overflow-wrap: anywhere;

		&::after {
			content: '';
			position: absolute;
			inset: 0;
		}

		&:focus-visible {
			outline: none;

			&::after {
				outline: @outline-base--focus;
				outline-offset: -2px;
			}
		}
	}

	&__body {
		overflow: hidden;
		color: @color-subtle;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__footer {
		display: flex;
		align-items: baseline;
		justify-content: space-between;
		gap: @spacing-75;
		color: @color-subtle;
		font-size: @font-size-x-small;
	}

	&__links {
		display: flex;
		flex-wrap: wrap;
		gap: @spacing-75;
		min-width: 0;
	}

	&__link {
		position: relative;
		z-index: 1;
	}

	&__time {
		flex-shrink: 0;
		white-space: nowrap;
	}

	&__dot {
		flex-shrink: 0;
		width: @spacing-50;
		height: @spacing-50;
		margin-top: @spacing-35;
		border-radius: @border-radius-circle;
		background-color: @background-color-progressive;
	}

	&--unread &__header {
		font-weight: @font-weight-bold;
	}

	&--unread &__icon {
		background-color: @background-color-progressive-subtle;
		color: @color-progressive;
	}
}
</style>
