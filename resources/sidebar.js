$( () => {
	const customSidebar = $( '#MenuSidebar' );
	if ( customSidebar.length ) {
		const sidebarContent = $( '#mw-panel' );
		if ( sidebarContent.length ) {
			const firstSection = sidebarContent.find( '.mw-portlet' ).first();
			if ( firstSection.length ) {
				firstSection.before( customSidebar );
			} else {
				sidebarContent.prepend( customSidebar );
			}
			customSidebar.show();
		} else {
			customSidebar.show();
		}
	}
} );
