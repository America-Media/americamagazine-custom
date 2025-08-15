/* global tp americaSettings localStorage  Coral */
( function () {
	// Set up objects
	tp = window.tp || [];
	if ( ! window.americaUtils ) {
		window.americaUtils = {};
	}
	const americaUtils = window.americaUtils;

	americaUtils.toggleHidden = function ( className ) {
		const toggleElements = document.getElementsByClassName( className );
		for ( const e of toggleElements ) {
			e.classList.toggle( 'hide' );
		}
	};

	americaUtils.getCoralToken = async function () {
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

	if ( americaSettings.coral ) {
		// Set up the coral embed
		americaUtils.coralEmbed = Coral.createStreamEmbed( {
			id: 'coral-thread',
			autoRender: false,
			rootURL: americaSettings.coral.coralRootURL,
			storyID: americaSettings.coral.storyID,
			storyURL: americaSettings.coral.localMode
				? undefined // In local mode, avoid storing invalid URLs with Coral
				: americaSettings.coral.storyURL,
			events: ( events ) => {
				events.on( 'loginPrompt', americaUtils.showLogin );
			},
		} );

		// Wire up the comments toggle
		document
			.getElementById( 'coral-comments-toggle' )
			.addEventListener( 'click', function () {
				if ( ! americaUtils.coralEmbed.rendered ) {
					// TODO consider loading animation before we start the Coral token process
					// getCoralToken depends on login state, so it cannot run before Piano inits
					tp.push( [
						'init',
						() => {
							americaUtils
								.getCoralToken()
								.then( ( token ) => {
									americaUtils.coralEmbed.config.accessToken =
										token;
								} )
								.then( () => {
									window.americaUtils.coralEmbed.render();
								} );
						},
					] );
				}
				americaUtils.toggleHidden( 'coral-comments-show-hide' );
			} );

		tp.push( [
			'addHandler',
			'loginSuccess',
			function () {
				if ( americaUtils.coralEmbed.rendered ) {
					americaUtils.getCoralToken().then( ( token ) => {
						americaUtils.coralEmbed.login( token );
					} );
				}
			},
		] );
	}
} )();
