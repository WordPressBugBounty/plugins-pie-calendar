const { __, getLocaleData } = wp.i18n;
const { PluginDocumentSettingPanel } = wp.editPost;
const { compose } = wp.compose;
const { withSelect, withDispatch } = wp.data;
const { ToggleControl, DateTimePicker, PanelRow, Button, Dropdown, TextControl } = wp.components;
const localeCode = getLocaleData()[""].lang ?? 'en-us';
 
const Piecal_Gutenberg_Sidebar_Plugin = ( { postType, postMeta, setPostMeta } ) => {

	// EDD downloads & WooCo products are not supported by default
	if( ( piecalGbVars.isWooActive || piecalGbVars.isEddActive ) && ( postType == "product" || postType == "download" ) ) return null;
	if( piecalGbVars.explicitAllowedPostTypes && piecalGbVars.explicitAllowedPostTypes.length > 0 && !piecalGbVars.explicitAllowedPostTypes.includes( postType ) ) return null;

	return(
		<PluginDocumentSettingPanel title={ __( 'Calendar', 'piecal') } initialOpen="true">
			<PanelRow>
				<ToggleControl
					label={ __( 'Show On Calendar', 'piecal' ) }
					onChange={ ( value ) => setPostMeta( { _piecal_is_event: value } ) }
					checked={ postMeta._piecal_is_event }
				/>
			</PanelRow>
			<PanelRow>
				{
					postMeta._piecal_is_event &&
						<ToggleControl
						label={ __( 'All Day Event', 'piecal' ) }
						onChange={ ( value ) => setPostMeta( { _piecal_is_allday: value } ) }
						checked={ postMeta._piecal_is_allday }
						/>
				}
			</PanelRow>
			{
				postMeta._piecal_is_event &&
				<PanelRow>
					<Dropdown
					className="piecal-gb-dropdown-container"
					contentClassName="piecal-gb-dropdown-content"
					position="bottom right"
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Button
							variant="primary"
							onClick={ onToggle }
							aria-expanded={ isOpen }
						>
							{ ( postMeta._piecal_start_date == '' || postMeta._piecal_start_date == null ) ? 
								<span>
									<span class="dashicons dashicons-calendar"></span>
									&nbsp; { __( 'Start Date', 'piecal' ) }
								</span>
							   : <span>
									<span class="dashicons dashicons-yes"></span>
									&nbsp; { __( 'Start Date', 'piecal' ) }
								</span>
							}
						</Button>
					) }
					renderContent={ () => 
						<div>
							<DateTimePicker
								currentDate={ postMeta._piecal_start_date }
								is12Hour={ true }
								label={ __( 'Start Date', 'piecal' ) }
								value={ postMeta._piecal_start_date }
								onChange={ ( value ) => setPostMeta( { _piecal_start_date: value } ) }
							/>
							<PanelRow>
								<Button
								variant="link"
								className="piecal-clear-date-button"
								isDestructive="true"
								onClick={ ( value ) => setPostMeta( { _piecal_start_date: null } ) }
								>
								{ __( 'Clear', 'piecal' ) }
								</Button>
							</PanelRow>
						</div>
					}
					/>
					<Dropdown
					className="piecal-gb-dropdown-container"
					contentClassName="piecal-gb-dropdown-content"
					position="bottom right"
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Button
							variant="primary"
							onClick={ onToggle }
							aria-expanded={ isOpen }
						>
							{ ( postMeta._piecal_end_date == '' || postMeta._piecal_end_date == null ) ? 
								<span>
									<span class="dashicons dashicons-calendar"></span>
									&nbsp; { __( 'End Date', 'piecal' ) }
								</span>
							   : <span>
									<span class="dashicons dashicons-yes"></span>
									&nbsp; { __( 'End Date', 'piecal' ) }
								</span>
							}
						</Button>
					) }
					renderContent={ () => 
						<div>
							<DateTimePicker
								currentDate={ postMeta._piecal_end_date }
								is12Hour={ true }
								label={ __( 'End Date', 'piecal' ) }
								value={ postMeta._piecal_end_date }
								onChange={ ( value ) => setPostMeta( { _piecal_end_date: value } ) }
							/>
							<PanelRow>
								<Button
									variant="link"
									className="piecal-clear-date-button"
									isDestructive="true"
									onClick={ ( value ) => setPostMeta( { _piecal_end_date: null } ) }
								>
							    { __( 'Clear', 'piecal' ) }
								</Button>
							</PanelRow>
						</div>
					}
					/>
				</PanelRow>
			}
			{
				( postMeta._piecal_start_date != '' && postMeta._piecal_start_date != null ) &&
				<PanelRow>
					<p>{ __('Starts on ', 'piecal') + new Date(postMeta._piecal_start_date).toLocaleDateString(localeCode.replace('_', '-'), { weekday:"long", year:"numeric", month:"short", day:"numeric"}) }</p>
				</PanelRow>
			}
			{
				( postMeta._piecal_end_date != '' && postMeta._piecal_end_date != null ) &&
				<PanelRow>
					<p>{ __('Ends on ', 'piecal') + new Date(postMeta._piecal_end_date).toLocaleDateString(localeCode.replace('_', '-'), { weekday:"long", year:"numeric", month:"short", day:"numeric"}) }</p>
				</PanelRow>
			}
		</PluginDocumentSettingPanel>
	);
}
 
export default compose( [
	withSelect( ( select ) => {		
		return {
			postMeta: select( 'core/editor' ).getEditedPostAttribute( 'meta' ),
			postType: select( 'core/editor' ).getCurrentPostType(),
		};
	} ),
	withDispatch( ( dispatch ) => {
		return {
			setPostMeta( newMeta ) {
				dispatch( 'core/editor' ).editPost( { meta: newMeta } );
			}
		};
	} )
] )( Piecal_Gutenberg_Sidebar_Plugin );