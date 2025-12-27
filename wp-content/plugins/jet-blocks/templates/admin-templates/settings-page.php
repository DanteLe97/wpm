<?php
/**
 * Main dashboard template. Note: Legacy template
 */
?><div id="jet-blocks-settings-page">
	<div class="jet-blocks-settings-page">
		<h1 class="cs-vui-title"><?php _e( 'JetBlocks Settings', 'jet-blocks' ); // phpcs:ignore ?></h1>
		<div class="cx-vui-panel">
			<cx-vui-tabs
				:in-panel="false"
				value="general-settings"
				layout="vertical">

				<?php do_action( 'jet-blocks/settings-page-template/tabs-start' ); ?>

				<cx-vui-tabs-panel
					name="general-settings"
					label="<?php _e( 'General Settings', 'jet-blocks' ); // phpcs:ignore ?>"
					key="general-settings">

					<cx-vui-select
						name="widgets_load_level"
						label="<?php _e( 'Editor Load Level', 'jet-blocks' ); // phpcs:ignore ?>"
						description="<?php _e( 'Choose a certain set of options in the widget’s Style tab by moving the slider, and improve your Elementor editor performance by selecting appropriate style settings fill level (from None to Full level)', 'jet-blocks' ); // phpcs:ignore ?>"
						:wrapper-css="[ 'equalwidth' ]"
						size="fullwidth"
						:options-list="pageOptions.widgets_load_level.options"
						v-model="pageOptions.widgets_load_level.value">
					</cx-vui-select>

				</cx-vui-tabs-panel>

				<cx-vui-tabs-panel
					name="breadcrumb-settings"
					label="<?php _e( 'Breadcrumb Settings', 'jet-blocks' ); // phpcs:ignore ?>"
					key="breadcrumb-settings">
						<div class="cx-vui-subtitle"><?php _e( 'Taxonomy to show in breadcrumbs for content types', 'jet-blocks' ); // phpcs:ignore ?></div>

						<?php
						$post_types = get_post_types( array( 'public' => true ), 'objects' );

						if ( is_array( $post_types ) && ! empty( $post_types ) ) {

							foreach ( $post_types as $post_type ) {
								$taxonomies = get_object_taxonomies( $post_type->name, 'objects' );

								if ( is_array( $taxonomies ) && ! empty( $taxonomies ) ) {

									$post_type_name = 'breadcrumbs_taxonomy_' . $post_type->name;

									?>
                                    <?php
                                    $pt_name        = isset( $post_type_name ) ? (string) $post_type_name : '';
                                    $pt_name_attr   = esc_attr( $pt_name );
                                    $pt_name_json   = wp_json_encode( $pt_name );
                                    $pt_label_attr  = isset( $post_type->label ) ? esc_attr( $post_type->label ) : '';
                                    ?>
                                <cx-vui-select
										name="<?php echo $pt_name_attr; // phpcs:ignore ?>"
										label="<?php echo $pt_label_attr; // phpcs:ignore ?>"
										:wrapper-css="[ 'equalwidth' ]"
										size="fullwidth"
										:options-list="pageOptions['<?php echo $pt_name_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>']['options']"
										v-model="pageOptions['<?php echo $pt_name_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>']['value']"
									></cx-vui-select><?php
								}
							}
						}
				?></cx-vui-tabs-panel>

				<cx-vui-tabs-panel
					name="available-widgets"
					label="<?php _e( 'Available Widgets', 'jet-blocks' ); // phpcs:ignore ?>"
					key="available-widgets">

					<div class="jet-blocks-settings-page__disable-all-widgets">
						<div class="cx-vui-component__label">
							<span v-if="disableAllWidgets"><?php _e( 'Disable All Widgets', 'jet-blocks' ); // phpcs:ignore ?></span>
							<span v-if="!disableAllWidgets"><?php _e( 'Enable All Widgets', 'jet-blocks' ); // phpcs:ignore ?></span>
						</div>

						<cx-vui-switcher
							name="disable-all-avaliable-widgets"
							:prevent-wrap="true"
							:return-true="true"
							:return-false="false"
							@input="disableAllWidgetsEvent"
							v-model="disableAllWidgets">
						</cx-vui-switcher>
					</div>

					<div class="jet-blocks-settings-page__avaliable-controls">
						<div
							class="jet-blocks-settings-page__avaliable-control"
							v-for="(option, index) in pageOptions.avaliable_widgets.options">
							<cx-vui-switcher
								:key="index"
								:name="`avaliable-widget-${option.value}`"
								:label="option.label"
								:wrapper-css="[ 'equalwidth' ]"
								return-true="true"
								return-false="false"
								v-model="pageOptions.avaliable_widgets.value[option.value]"
							>
							</cx-vui-switcher>
						</div>
					</div>

				</cx-vui-tabs-panel>

				<cx-vui-tabs-panel
					name="available-extensions"
					label="<?php _e( 'Available Extensions', 'jet-blocks' ); // phpcs:ignore ?>"
					key="available-extensions">

					<div class="jet-blocks-settings-page__avaliable-controls">
						<div
							class="jet-blocks-settings-page__avaliable-control"
							v-for="(option, index) in pageOptions.avaliable_extensions.options">
							<cx-vui-switcher
								:key="index"
								:name="`avaliable-extension-${option.value}`"
								:label="option.label"
								:wrapper-css="[ 'equalwidth' ]"
								return-true="true"
								return-false="false"
								v-model="pageOptions.avaliable_extensions.value[option.value]"
							>
							</cx-vui-switcher>
						</div>
					</div>

				</cx-vui-tabs-panel>

				<?php do_action( 'jet-blocks/settings-page-template/tabs-end' ); ?>
			</cx-vui-tabs>
		</div>
	</div>
</div>
