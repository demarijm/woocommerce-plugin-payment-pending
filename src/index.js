/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { H, Section, Table, WebPreview } from '@woocommerce/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './index.scss';

const addCurrencyFilters = ( filters ) => {
	return [
		{
			label: __( 'Currency', 'dev-blog-example' ),
			staticParams: [],
			param: 'currency',
			showFilters: () => true,
			defaultValue: 'USD',
			filters: [ ...( wcSettings.multiCurrency || [] ) ],
		},
		...filters,
	];
};

addFilter(
	'woocommerce_admin_orders_report_filters',
	'dev-blog-example',
	addCurrencyFilters
);

const addTableColumn = ( reportTableData ) => {
	if ( 'orders' !== reportTableData.endpoint ) {
		return reportTableData;
	}

	const newHeaders = [
		{
			label: 'Currency',
			key: 'currency',
		},
		...reportTableData.headers,
	];
	const newRows = reportTableData.rows.map( ( row, index ) => {
		const item = reportTableData.items.data[ index ];
		const newRow = [
			{
				display: item.currency,
				value: item.currency,
			},
			...row,
		];
		return newRow;
	} );
	reportTableData.headers = newHeaders;
	reportTableData.rows = newRows;

	return reportTableData;
};

addFilter(
	'woocommerce_admin_report_table',
	'dev-blog-example',
	addTableColumn
);

const Index = () => {
	const [ allCustomers, setAllCustomers ] = useState( [] );
	const mounted = useRef( true );

	const fetchAllCustomers = async (
		setAllCustomers = () => void null
	) => {
		const reviewsUrl = '/wc/v3/customers';

		await wp
			.apiFetch( {
				path: reviewsUrl,
			} )
			.then( ( data ) => {
				console.log('data', data);
				setAllCustomers( data );
			} );
	};

	useEffect( () => {
		const updateState = ( allCustomers ) => {
			if ( mounted.current ) {
				setAllCustomers( allCustomers );
			}
		};
		fetchAllCustomers( updateState );
		console.log( 'show data if not null', allCustomers );

		return () => {
			mounted.current = false;
		};
	}, [] );

	const headers = [
		{ label: 'Product ID' },
		{ label: 'Rating' },
		{ label: 'Comments' },
	];

	const rows = [
		[
			{ display: 'January', value: 1 },
			{ display: 10, value: 10 },
			{ display: '$530.00', value: 530 },
		],
		[
			{ display: 'February', value: 2 },
			{ display: 13, value: 13 },
			{ display: '$675.00', value: 675 },
		],
		[
			{ display: 'March', value: 3 },
			{ display: 9, value: 9 },
			{ display: '$460.00', value: 460 },
		],
	];

	return (
		<>
			<H>My Example Extension</H>
			<Section component="article">
				<Table
					caption="Recent Reviews"
					rows={ rows }
					headers={ headers }
				/>
			</Section>
		</>
	);
};

addFilter( 'woocommerce_admin_pages_list', 'my-namespace', ( pages ) => {
	pages.push( {
		container: Index,
		path: '/pending-report',
		breadcrumbs: [ 'Payment Pending Report' ],
		navArgs: {
			id: 'pending-report',
		},
	} );

	return pages;
} );
