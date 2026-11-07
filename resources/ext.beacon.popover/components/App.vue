<template>
	<cdx-popover
		v-model:open="store.isOpen"
		class="beacon-popover"
		:anchor="anchor"
		:placement="placement"
		:render-in-place="false"
	>
		<div class="beacon-card">
			<h2 class="beacon-card__header">{{ $i18n( 'beacon-popover-title' ).text() }}</h2>
			<div class="beacon-card__body" :aria-busy="store.isLoading">
				<ul class="beacon-card__list"></ul>
			</div>
		</div>
	</cdx-popover>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { useNotificationStore } = require( '../notifications.js' );
const { CdxPopover } = require( '../codex.js' );
module.exports = defineComponent({
	name: 'Notifications',
	components: {
		CdxPopover
	},
	props: {
		anchor: { type: HTMLElement, required: true }
	},
	setup() {
		const store = useNotificationStore();

		return {
			store,
			placement: 'bottom-end'
		}
	}
} );
</script>

<style lang="less">

</style>
