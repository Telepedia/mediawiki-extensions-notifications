<template>
	<cdx-popover
		v-model:open="store.isOpen"
		class="beacon-popover"
		:anchor="anchor"
		:placement="placement"
		:render-in-place="false"
	>
		<div class="beacon-card">
			<div class="beacon-card__header">
				<h2 class="beacon-card__title">{{ $i18n( 'beacon-popover-title' ).text() }}</h2>
				<cdx-button
					v-if="store.unreadCount > 0 && store.notifications && store.notifications.length"
					weight="quiet"
					size="small"
					@click="store.markAllRead()"
				>
					<cdx-icon :icon="cdxIconCheckAll"></cdx-icon>
					{{ $i18n( 'beacon-popover-mark-all-read' ).text() }}
				</cdx-button>
			</div>
			<div class="beacon-card__body" :aria-busy="store.isLoading ? 'true' : 'false'">
				<ul v-if="store.isLoading && !store.notifications" class="beacon-card__list">
					<li
						v-for="n in 3"
						:key="n"
						class="beacon-card__item"
						aria-hidden="true"
					>
						<span class="beacon-card__icon beacon-skeleton"></span>
						<span class="beacon-card__text">
							<span class="beacon-skeleton beacon-skeleton--title"></span>
							<span class="beacon-skeleton beacon-skeleton--line"></span>
							<span class="beacon-skeleton beacon-skeleton--line beacon-card__time"></span>
						</span>
					</li>
				</ul>
				<div v-else-if="store.error && !store.notifications" class="beacon-card__empty" role="alert">
					<p>{{ $i18n( 'beacon-popover-error' ).text() }}</p>
					<cdx-button size="small" @click="store.fetch()">
						{{ $i18n( 'beacon-popover-retry' ).text() }}
					</cdx-button>
				</div>
				<p v-else-if="!store.notifications || !store.notifications.length" class="beacon-card__empty">
					{{ $i18n( 'beacon-popover-empty' ).text() }}
				</p>
				<ul v-else class="beacon-list">
					<notification-item
						v-for="notification in store.notifications"
						:key="notification.id"
						:notification="notification"
						@mark-read="markNotificationRead"
						@mark-unread="store.markUnread( $event )"
					></notification-item>
				</ul>
			</div>
			<div class="beacon-card__footer">
				<a
					class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled beacon-card__all"
					:href="allUrl"
				>
					<cdx-icon :icon="cdxIconHistory"></cdx-icon>
					{{ $i18n( 'beacon-popover-all' ).text() }}
				</a>
				<a
					class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--icon-only"
					:href="preferencesUrl"
					:title="$i18n( 'beacon-popover-preferences' ).text()"
					:aria-label="$i18n( 'beacon-popover-preferences' ).text()"
				>
					<cdx-icon :icon="cdxIconSettings"></cdx-icon>
				</a>
			</div>
		</div>
	</cdx-popover>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { useNotificationStore } = require( '../notifications.js' );
const { CdxButton, CdxIcon, CdxPopover } = require( '../codex.js' );
const { cdxIconCheckAll, cdxIconHistory, cdxIconSettings } = require( '../icons.json' );
const NotificationItem = require( './NotificationItem.vue' );
module.exports = defineComponent({
	name: 'Notifications',
	components: {
		CdxButton,
		CdxIcon,
		CdxPopover,
		NotificationItem
	},
	props: {
		anchor: { type: HTMLElement, required: true }
	},
	setup() {
		const store = useNotificationStore();

		async function markNotificationRead( id ) {
			await store.markRead( id );
		}

		return {
			store,
			placement: 'bottom-end',
			allUrl: mw.util.getUrl( 'Special:Notifications' ),
			preferencesUrl: mw.util.getUrl( 'Special:Preferences' ) + '#mw-prefsection-beacon',
			cdxIconCheckAll,
			cdxIconHistory,
			cdxIconSettings,
			markNotificationRead
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.cdx-popover.beacon-popover {
	// copy the same width as echo, but we may need to change this as it looks a bit funky
	width: 31.25rem;
	max-width: calc( 100vw - 1rem );
	max-height: calc( 100vh - 4rem );
	padding: 0;
	border: @border-width-base @border-style-base @border-color-subtle;
	border-radius: @border-radius-base;
	background-color: @background-color-base;
	box-shadow: @box-shadow-large;
	overflow: hidden;

	.cdx-popover__arrow {
		display: none;
	}
}

@beacon-row-height: ~'( @{size-100} + 2 * @{size-75} + 2 * @{spacing-35} + 2 * @{spacing-75} )';

.beacon-card {
	display: flex;
	flex-direction: column;
	min-height: 0;
	color: @color-base;
	font-size: @font-size-medium;

	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: @spacing-50;
		min-height: @size-150;
		padding: @spacing-75 @spacing-100;
		border-bottom: @border-width-base @border-style-base @border-color-subtle;
	}

	&__title {
		margin: 0;
		padding: 0;
		border: 0;
		font-family: inherit;
		font-size: @font-size-medium;
		font-weight: @font-weight-bold;
		line-height: @line-height-small;
	}

	&__body {
		display: flex;
		flex: 1 1 auto;
		flex-direction: column;
		min-height: calc( 3 * @beacon-row-height + 2 * @border-width-base );
		overflow-y: auto;
	}

	&__list {
		margin: 0;
		padding: 0;
		list-style: none;
	}

	&__item {
		display: flex;
		align-items: flex-start;
		gap: @spacing-75;
		margin: 0;
		padding: @spacing-75 @spacing-100;

		+ .beacon-card__item {
			border-top: @border-width-base @border-style-base @border-color-subtle;
		}
	}

	&__icon {
		flex-shrink: 0;
		width: @size-200;
		height: @size-200;
	}

	&__text {
		display: flex;
		flex-direction: column;
		flex-grow: 1;
		gap: @spacing-35;
		min-width: 0;
	}

	&__time.beacon-skeleton--line {
		width: 30%;
	}

	&__footer {
		display: flex;
		flex-shrink: 0;
		gap: @spacing-50;
		padding: @spacing-75 @spacing-100;
		border-top: @border-width-base @border-style-base @border-color-subtle;
	}

	&__all {
		flex-grow: 1;
	}

	& &__footer a.cdx-button .cdx-icon {
		width: @size-icon-medium;
		min-width: @size-icon-medium;
		height: @size-icon-medium;
		min-height: @size-icon-medium;
		padding: 0;
	}

	&__empty {
		margin: auto 0;
		padding: @spacing-150 @spacing-100;
		color: @color-subtle;
		font-size: @font-size-small;
		text-align: center;

		p {
			margin: 0 0 @spacing-75;
		}
	}
}

.beacon-skeleton {
	display: block;
	border-radius: @border-radius-base;
	background-color: @background-color-neutral;

	&--title {
		width: 60%;
		height: @size-100;
	}

	&--line {
		width: 90%;
		height: @size-75;
	}
}

@media ( prefers-reduced-motion: no-preference ) {
	.beacon-skeleton {
		background-image: linear-gradient(
			90deg,
			@background-color-neutral 0,
			@background-color-neutral-subtle 50%,
			@background-color-neutral 100%
		);
		background-repeat: no-repeat;
		background-size: 200% 100%;
		animation: beacon-skeleton-shimmer 1.2s ease-in-out infinite;
	}
}

@keyframes beacon-skeleton-shimmer {
	from {
		background-position: 150% 0;
	}

	to {
		background-position: -50% 0;
	}
}
</style>
