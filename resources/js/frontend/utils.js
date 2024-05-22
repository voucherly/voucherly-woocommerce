import { getSetting } from '@woocommerce/settings';

export const getBlocksConfiguration = () => {
	const voucherlyServerData = getSetting( 'voucherly_data', null );

	if ( ! voucherlyServerData ) {
		throw new Error( 'Voucherly initialization data is not available' );
	}

	return voucherlyServerData;
};