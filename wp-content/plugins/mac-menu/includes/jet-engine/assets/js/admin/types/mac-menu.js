(function( $ ) {

	'use strict';

	// Đảm bảo Vue đã được load
	if ( typeof Vue === 'undefined' ) {
		console.error( 'Mac Menu Query: Vue is not defined!' );
		return;
	}

	// Đảm bảo JetQueryWatcherMixin đã được load
	if ( typeof window.JetQueryWatcherMixin === 'undefined' ) {
		console.error( 'Mac Menu Query: JetQueryWatcherMixin is not defined!' );
		return;
	}

	Vue.component( 'jet-mac-menu-query', {
		template: '#jet-mac-menu-query',
		// KHÔNG dùng JetQueryWatcherMixin để tránh infinite loop
		// Tự implement watch với flag
		props: [ 'value', 'dynamic-value' ],
		data: function() {
			return {
				query: {},
				dynamicQuery: {},
				_isInitializing: true, // Flag để tránh emit khi đang init
			};
		},
		methods: {
			defaultQuery: function() {
				return {
					parents_only: true,
					parent_id: '',
					search_ids: '',
					search: '',
					order_by: 'order',
					order: 'ASC',
					limit: '',
					offset: 0,
				};
			},
			handleSearchIdsChange: function( value ) {
				console.log( 'Mac Menu Query: handleSearchIdsChange called', value );
				this.$set( this.query, 'search_ids', value );
				// Force emit
				if ( ! this._isInitializing ) {
					this.$emit( 'input', this.query );
				}
			},
			handleSearchChange: function( value ) {
				console.log( 'Mac Menu Query: handleSearchChange called', value );
				this.$set( this.query, 'search', value );
				// Force emit
				if ( ! this._isInitializing ) {
					this.$emit( 'input', this.query );
				}
			},
		},
		watch: {
			query: {
				handler: function( newVal, oldVal ) {
					// Chỉ emit nếu không phải đang init
					if ( ! this._isInitializing ) {
						// Luôn emit để đảm bảo giá trị được lưu, kể cả string rỗng
						this.$emit( 'input', newVal );
					}
				},
				deep: true,
				immediate: false,
			},
			// Watch riêng cho search_ids và search để đảm bảo emit khi thay đổi
			'query.search_ids': {
				handler: function( newVal, oldVal ) {
					if ( ! this._isInitializing ) {
						console.log( 'Mac Menu Query: search_ids changed', newVal );
						this.$emit( 'input', this.query );
					}
				},
				immediate: false,
			},
			'query.search': {
				handler: function( newVal, oldVal ) {
					if ( ! this._isInitializing ) {
						console.log( 'Mac Menu Query: search changed', newVal );
						this.$emit( 'input', this.query );
					}
				},
				immediate: false,
			},
			dynamicQuery: {
				handler: function( newVal, oldVal ) {
					if ( ! this._isInitializing && oldVal && JSON.stringify( newVal ) !== JSON.stringify( oldVal ) ) {
						this.$emit( 'dynamic-input', newVal );
					}
				},
				deep: true,
				immediate: false,
			},
		},
		created: function() {
			// Set flag để tránh emit khi init
			this._isInitializing = true;
			
			// Initialize query từ value prop hoặc default
			if ( this.value && typeof this.value === 'object' && Object.keys( this.value ).length > 0 ) {
				this.query = Object.assign( {}, this.defaultQuery(), this.value );
				console.log( 'Mac Menu Query: Loaded from value', this.query );
			} else {
				this.query = this.defaultQuery();
				console.log( 'Mac Menu Query: Using default', this.query );
			}
			
			// Initialize dynamicQuery
			if ( this.dynamicValue ) {
				this.dynamicQuery = Object.assign( {}, this.dynamicValue );
			} else {
				this.dynamicQuery = {};
			}
		},
		mounted: function() {
			// Sau khi mounted, cho phép emit
			var self = this;
			this.$nextTick( function() {
				self._isInitializing = false;
				// Luôn emit initial value để đảm bảo được lưu
				self.$emit( 'input', self.query );
			} );
		},
	});

})( jQuery );

