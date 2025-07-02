/* global tp localStorage AmericaCoralSettings Coral */
( function () {
	const getCoralToken = async function () {
		if ( ! tp.pianoId.isUserValid() ) {
			localStorage.removeItem( 'america-coral-token' );
			return null;
		}
		const coralTokenStorage = JSON.parse(
			localStorage.getItem( 'america-coral-token' )
		);

		// Check if stored Coral token is still valid relative to Piano user token
		if (
			coralTokenStorage &&
			coralTokenStorage.exp >= tp.pianoId.getUser().exp &&
			coralTokenStorage.uid === tp.pianoId.getUser().uid
		) {
			return coralTokenStorage.token;
		}

		// If we are here, the token is not valid, so remove it
		localStorage.removeItem( 'america-coral-token' );

		// Request a new token from the plugin's endpoint
		return fetch(
			`/wp-json/america-magazine/coral-token?piano-jwt=${ tp.pianoId.getToken() }`
		)
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error( `HTTP error: ${ response.status }` );
				}
				return response.json();
			} )
			.then( ( coralToken ) => {
				localStorage.setItem(
					'america-coral-token',
					JSON.stringify( {
						exp: tp.pianoId.getUser().exp,
						uid: tp.pianoId.getUser().uid,
						token: coralToken,
					} )
				);
				return coralToken;
			} )
			.catch( () => {
				return null;
			} );
	};

	if ( AmericaCoralSettings ) {
		window.americaCoralEmbed = Coral.createStreamEmbed( {
			id: 'coral-thread',
			autoRender: false,
			rootURL: AmericaCoralSettings.coralRootURL,
			storyID: AmericaCoralSettings.storyID,
			storyURL: AmericaCoralSettings.localMode
				? undefined // In local mode, avoid storing invalid URLs with Coral
				: AmericaCoralSettings.storyURL,
		} );

		document
			.getElementById( 'coral-comments-toggle' )
			.addEventListener( 'click', function () {
				if ( ! window.americaCoralEmbed.rendered ) {
					getCoralToken()
						.then( ( token ) => {
							return ( window.americaCoralEmbed.config.accessToken =
								token );
						} )
						.then( () => {
							window.americaCoralEmbed.render();
						} );
				}
				const toggleElements = document.getElementsByClassName(
					'coral-comments-show-hide'
				);
				for ( const e of toggleElements ) {
					e.hidden = ! e.hidden;
				}
			} );
	}
} )();
