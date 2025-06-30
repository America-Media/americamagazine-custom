/* global AmericaCoralSettings Coral */
( function () {
	if ( AmericaCoralSettings ) {
		window.coralEmbed = Coral.createStreamEmbed( {
			id: 'coral_thread',
			autoRender: true,
			rootURL: AmericaCoralSettings.coralRootURL,
			storyID: AmericaCoralSettings.storyID,
			storyURL: AmericaCoralSettings.localMode
				? undefined // In local mode, avoid storing invalid URLs with Coral
				: AmericaCoralSettings.storyURL,
			events( events ) {
				events.on( 'loginPrompt', function () {
					return true; // eventually intended to be AmericaPianoUtils.showLogin()
				} );
			},
			accessToken: null, // eventually intended to be AmericaPianoUtils.getCoralToken()
		} );
	}
} )();
