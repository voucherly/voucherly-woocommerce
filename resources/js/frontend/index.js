
import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

const settings = getSetting( 'voucherly_data', {} );

const iconUrl = settings.icon;

const label = decodeEntities( settings.title ) || "Voucherly";
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
	const { PaymentMethodLabel } = props.components;
	const icon = <img src={iconUrl} alt={label} name={label} />
	return <PaymentMethodLabel text={label} icon={icon} />;
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
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
	icon: settings.icon
};

registerPaymentMethod( Voucherly );
