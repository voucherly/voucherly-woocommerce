
import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { getBlocksConfiguration } from './utils';

const settings = getBlocksConfiguration();

const icon = settings.icon;

const labelText = decodeEntities( settings.title ) || "Voucherly";
/**
 * Content component
 */
const Content = () => {
	return decodeEntities( settings.description || '' );
};
/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = ( props ) => {
	const { PaymentMethodLabel, PaymentMethodIcons } = props.components;

	var icons = getBlocksConfiguration().icons ?? [];
	if (icons.length > 0) {

		return <div style={{display: "flex", flexDirection: "row-reverse"}}>
			{ labelText }
			<PaymentMethodIcons icons={ icons } align="left" />
		</div>;
	}
		
	const iconElement = <img src={ icon } alt="Voucherly" name="Voucherly" />
	return <PaymentMethodLabel text={ labelText } icon={ iconElement } />;
};

/**
 * Dummy payment method config object.
 */
const Voucherly = {
	name: "voucherly",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: "Voucherly",
	supports: {
		features: settings.supports,
	},
	icon: icon
};

registerPaymentMethod( Voucherly );
