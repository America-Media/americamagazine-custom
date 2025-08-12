/* global americaSettings tp sessionStorage localStorage */
( function () {
	// Set up objects
	tp = window.tp || [];
	if ( ! window.americaUtils ) {
		window.americaUtils = {};
	}
	const americaUtils = window.americaUtils;

	// Wrap tp's callApi function in a Promise
	americaUtils.callPianoApi = function ( endpoint, args ) {
		return new Promise( ( resolve, reject ) => {
			tp.api.callApi( endpoint, args, ( response ) => {
				// Piano API returns a code of 0 for a successful call
				if ( response.code !== 0 ) {
					reject( response );
				} else {
					resolve( response );
				}
			} );
		} );
	};

	americaUtils.showLogin = function ( e = null ) {
		if ( e ) {
			e.preventDefault();
		}
		tp.pianoId.show( { screen: 'login' } );
	};

	// Check if the currently logged in user has subscriber access
	americaUtils.getSubscriberAccess = function () {
		return new Promise( ( resolve, reject ) => {
			// Can only get subscriber access for logged in users
			if ( ! tp.pianoId.isUserValid() ) {
				resolve( false );
			}
			// Check if we have a cached result
			const sessionStorageCheck = JSON.parse(
				sessionStorage.getItem( 'america-subscriber-access' )
			);
			if ( sessionStorageCheck ) {
				// Answer from cached session storage check
				resolve( sessionStorageCheck.access_id !== null );
			} else {
				// If no session storage item, then get it from the API
				americaUtils
					.callPianoApi( '/access/check', {
						rid: americaSettings.piano.subscriber_rid,
					} )
					.then( ( response ) => {
						sessionStorage.setItem(
							'america-subscriber-access',
							JSON.stringify( response.access )
						);
						resolve( response.access.access_id !== null );
					} )
					.catch( ( err ) => {
						reject( err );
					} );
			}
		} );
	};

	// Helper util to clear America items in local and session storage
	americaUtils.clearAmericaStorage = function () {
		[ localStorage, sessionStorage ].forEach( ( storage ) => {
			Object.keys( storage )
				.filter( ( k ) => k.startsWith( 'america-' ) )
				.forEach( ( k ) => storage.removeItem( k ) );
		} );
	};

	// Helper util to take an action on all elements matching a query selector
	americaUtils.forEachElementBySelector = function ( selector, callback ) {
		const elements = document.querySelectorAll( selector );
		for ( const e of elements ) {
			callback( e );
		}
	};

	// Modify the UX to reflect account state
	americaUtils.configureAccountUx = function () {
		if ( tp.pianoId.isUserValid() ) {
			// Identify logged in status to the whole page
			document.body.classList.add( 'piano-logged-in' );

			// The stock Piano plugin hides the login button, so we only need to unhide UX for logged-in users
			americaUtils.forEachElementBySelector(
				'.wp_piano_id_logged_in',
				( e ) => {
					e.classList.remove( 'hide' );
				}
			);

			// Wire the logout link to a Piano ID action
			americaUtils.forEachElementBySelector(
				'.wp_piano_id_logout',
				( e ) => {
					e.addEventListener( 'click', () => {
						americaUtils.clearAmericaStorage();
						tp.pianoId.logout();
						// After logout, return to homepage
						window.location.href = '/';
					} );
				}
			);

			// Adjust UX for subscribers
			americaUtils.getSubscriberAccess().then( ( subscribed ) => {
				if ( subscribed ) {
					document.body.classList.add( 'piano-subscriber' );
					americaUtils.forEachElementBySelector(
						'.wp_piano_subscribe_button',
						( e ) => {
							e.classList.add( 'hide' );
						}
					);
				}
			} );
		}
	};

	// Enqueue configure UX to run on init (handles user is already logged in)
	tp.push( [
		'init',
		() => {
			americaUtils.configureAccountUx();
		},
	] );
	// Enqueue configure UX to run on login (handles user logs in on this page)
	tp.push( [
		'addHandler',
		'loginSuccess',
		() => {
			americaUtils.configureAccountUx();
		},
	] );
} )();
