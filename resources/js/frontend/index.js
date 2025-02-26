
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

		icons = icons.map((icon) => ({ id: icon.id, alt: icon.name, src: icon.src }));

		return <div className="payment_method_voucherly_block">
			{ labelText }
			<PaymentMethodIcons icons={ icons } align="left" className="voucherly_icons" />
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
		showSavedCards: settings.showSavedCards,
		showSaveOption: settings.showSaveOption,
	},
	icon: icon
};

registerPaymentMethod( Voucherly );
