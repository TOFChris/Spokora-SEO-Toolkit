<?php
/**
 * Schema.org @type choices for global Organization / LocalBusiness JSON-LD.
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	die( 'Access denied.' );
}

/**
 * Grouped business type options (schema.org type => label).
 *
 * @return array<string, array<string, string>>
 */
function spokora_schema_type_choice_groups() {
	return array(
		__( 'General', 'spokora-seo-toolkit' ) => array(
			'Organization'        => __( 'Organization (generic)', 'spokora-seo-toolkit' ),
			'Corporation'         => __( 'Corporation / company', 'spokora-seo-toolkit' ),
			'LocalBusiness'       => __( 'Local business (generic)', 'spokora-seo-toolkit' ),
			'ProfessionalService' => __( 'Professional service / agency', 'spokora-seo-toolkit' ),
			'OnlineBusiness'      => __( 'Online business (generic)', 'spokora-seo-toolkit' ),
			'OnlineStore'         => __( 'Online store / e-commerce', 'spokora-seo-toolkit' ),
		),
		__( 'Technology & software', 'spokora-seo-toolkit' ) => array(
			'WebApplication'        => __( 'Web application / SaaS', 'spokora-seo-toolkit' ),
			'SoftwareApplication'   => __( 'Desktop / downloadable software', 'spokora-seo-toolkit' ),
			'MobileApplication'     => __( 'Mobile app (iOS / Android)', 'spokora-seo-toolkit' ),
			'VideoGame'             => __( 'Video game / game studio', 'spokora-seo-toolkit' ),
			'ResearchOrganization'  => __( 'Research / R&D organization', 'spokora-seo-toolkit' ),
			'ComputerStore'         => __( 'Computer store', 'spokora-seo-toolkit' ),
			'ElectronicsStore'      => __( 'Electronics store', 'spokora-seo-toolkit' ),
			'MobilePhoneStore'      => __( 'Mobile phone store', 'spokora-seo-toolkit' ),
			'InternetCafe'          => __( 'Internet caf� / coworking', 'spokora-seo-toolkit' ),
		),
		__( 'Professional & financial', 'spokora-seo-toolkit' ) => array(
			'AccountingService'  => __( 'Accounting / bookkeeping', 'spokora-seo-toolkit' ),
			'FinancialService'   => __( 'Financial services', 'spokora-seo-toolkit' ),
			'BankOrCreditUnion'  => __( 'Bank or credit union', 'spokora-seo-toolkit' ),
			'InsuranceAgency'    => __( 'Insurance agency', 'spokora-seo-toolkit' ),
			'LegalService'       => __( 'Legal services', 'spokora-seo-toolkit' ),
			'Attorney'           => __( 'Attorney / law firm', 'spokora-seo-toolkit' ),
			'Notary'             => __( 'Notary', 'spokora-seo-toolkit' ),
			'RealEstateAgent'    => __( 'Real estate agent / brokerage', 'spokora-seo-toolkit' ),
			'EmploymentAgency'   => __( 'Staffing / employment agency', 'spokora-seo-toolkit' ),
			'TravelAgency'       => __( 'Travel agency', 'spokora-seo-toolkit' ),
			'EngineeringService' => __( 'Engineering / technical consulting', 'spokora-seo-toolkit' ),
		),
		__( 'Health & wellness', 'spokora-seo-toolkit' ) => array(
			'Dentist'                   => __( 'Dentist', 'spokora-seo-toolkit' ),
			'Physician'                 => __( 'Physician / doctor', 'spokora-seo-toolkit' ),
			'MedicalClinic'             => __( 'Medical clinic', 'spokora-seo-toolkit' ),
			'MedicalOrganization'       => __( 'Medical organization', 'spokora-seo-toolkit' ),
			'Hospital'                  => __( 'Hospital', 'spokora-seo-toolkit' ),
			'DiagnosticLab'             => __( 'Diagnostic lab', 'spokora-seo-toolkit' ),
			'Pharmacy'                  => __( 'Pharmacy', 'spokora-seo-toolkit' ),
			'Optician'                  => __( 'Optician / eye care', 'spokora-seo-toolkit' ),
			'VeterinaryCare'            => __( 'Veterinary care', 'spokora-seo-toolkit' ),
			'HealthAndBeautyBusiness'   => __( 'Health & beauty (generic)', 'spokora-seo-toolkit' ),
			'DaySpa'                    => __( 'Day spa', 'spokora-seo-toolkit' ),
			'HairSalon'                 => __( 'Hair salon', 'spokora-seo-toolkit' ),
			'BeautySalon'               => __( 'Beauty salon', 'spokora-seo-toolkit' ),
			'NailSalon'                 => __( 'Nail salon', 'spokora-seo-toolkit' ),
			'TattooParlor'              => __( 'Tattoo parlor', 'spokora-seo-toolkit' ),
			'ExerciseGym'               => __( 'Gym / fitness studio', 'spokora-seo-toolkit' ),
			'HealthClub'                => __( 'Health club', 'spokora-seo-toolkit' ),
		),
		__( 'Home, property & trades', 'spokora-seo-toolkit' ) => array(
			'GeneralContractor'             => __( 'General contractor', 'spokora-seo-toolkit' ),
			'Electrician'                   => __( 'Electrician', 'spokora-seo-toolkit' ),
			'Plumber'                       => __( 'Plumber', 'spokora-seo-toolkit' ),
			'HVACBusiness'                  => __( 'HVAC', 'spokora-seo-toolkit' ),
			'RoofingContractor'             => __( 'Roofing', 'spokora-seo-toolkit' ),
			'HousePainter'                  => __( 'House painter', 'spokora-seo-toolkit' ),
			'Locksmith'                     => __( 'Locksmith', 'spokora-seo-toolkit' ),
			'MovingCompany'                 => __( 'Moving company', 'spokora-seo-toolkit' ),
			'LandscapingBusiness'           => __( 'Landscaping', 'spokora-seo-toolkit' ),
			'HomeAndConstructionBusiness'   => __( 'Home & construction (generic)', 'spokora-seo-toolkit' ),
			'SelfStorage'                   => __( 'Self storage', 'spokora-seo-toolkit' ),
			'DryCleaningOrLaundry'          => __( 'Dry cleaning / laundry', 'spokora-seo-toolkit' ),
			'RecyclingCenter'               => __( 'Recycling center', 'spokora-seo-toolkit' ),
		),
		__( 'Retail', 'spokora-seo-toolkit' ) => array(
			'Store'                => __( 'Retail store (generic)', 'spokora-seo-toolkit' ),
			'GroceryStore'         => __( 'Grocery store', 'spokora-seo-toolkit' ),
			'ConvenienceStore'     => __( 'Convenience store', 'spokora-seo-toolkit' ),
			'DepartmentStore'      => __( 'Department store', 'spokora-seo-toolkit' ),
			'WholesaleStore'       => __( 'Wholesale store', 'spokora-seo-toolkit' ),
			'ClothingStore'        => __( 'Clothing store', 'spokora-seo-toolkit' ),
			'ShoeStore'            => __( 'Shoe store', 'spokora-seo-toolkit' ),
			'JewelryStore'         => __( 'Jewelry store', 'spokora-seo-toolkit' ),
			'FurnitureStore'       => __( 'Furniture store', 'spokora-seo-toolkit' ),
			'HomeGoodsStore'       => __( 'Home goods store', 'spokora-seo-toolkit' ),
			'HardwareStore'        => __( 'Hardware store', 'spokora-seo-toolkit' ),
			'SportingGoodsStore'   => __( 'Sporting goods store', 'spokora-seo-toolkit' ),
			'HobbyShop'            => __( 'Hobby shop', 'spokora-seo-toolkit' ),
			'BookStore'            => __( 'Bookstore', 'spokora-seo-toolkit' ),
			'Florist'              => __( 'Florist', 'spokora-seo-toolkit' ),
			'PetStore'             => __( 'Pet store', 'spokora-seo-toolkit' ),
			'LiquorStore'          => __( 'Liquor store', 'spokora-seo-toolkit' ),
			'BikeStore'            => __( 'Bike store', 'spokora-seo-toolkit' ),
			'OutletStore'          => __( 'Outlet store', 'spokora-seo-toolkit' ),
		),
		__( 'Food & drink', 'spokora-seo-toolkit' ) => array(
			'Restaurant'           => __( 'Restaurant', 'spokora-seo-toolkit' ),
			'CafeOrCoffeeShop'     => __( 'Caf� / coffee shop', 'spokora-seo-toolkit' ),
			'Bakery'               => __( 'Bakery', 'spokora-seo-toolkit' ),
			'BarOrPub'             => __( 'Bar or pub', 'spokora-seo-toolkit' ),
			'FastFoodRestaurant'   => __( 'Fast food', 'spokora-seo-toolkit' ),
			'IceCreamShop'         => __( 'Ice cream shop', 'spokora-seo-toolkit' ),
			'Brewery'              => __( 'Brewery', 'spokora-seo-toolkit' ),
			'Winery'               => __( 'Winery', 'spokora-seo-toolkit' ),
			'Distillery'           => __( 'Distillery', 'spokora-seo-toolkit' ),
			'FoodEstablishment'    => __( 'Food establishment (generic)', 'spokora-seo-toolkit' ),
		),
		__( 'Hospitality & events', 'spokora-seo-toolkit' ) => array(
			'Hotel'             => __( 'Hotel', 'spokora-seo-toolkit' ),
			'Motel'             => __( 'Motel', 'spokora-seo-toolkit' ),
			'BedAndBreakfast'   => __( 'Bed & breakfast', 'spokora-seo-toolkit' ),
			'Resort'            => __( 'Resort', 'spokora-seo-toolkit' ),
			'Campground'        => __( 'Campground', 'spokora-seo-toolkit' ),
			'LodgingBusiness'   => __( 'Lodging (generic)', 'spokora-seo-toolkit' ),
			'EventVenue'        => __( 'Event venue', 'spokora-seo-toolkit' ),
		),
		__( 'Automotive', 'spokora-seo-toolkit' ) => array(
			'AutomotiveBusiness' => __( 'Automotive business (generic)', 'spokora-seo-toolkit' ),
			'AutoDealer'         => __( 'Auto dealer', 'spokora-seo-toolkit' ),
			'MotorcycleDealer'   => __( 'Motorcycle dealer', 'spokora-seo-toolkit' ),
			'AutoRepair'         => __( 'Auto repair', 'spokora-seo-toolkit' ),
			'AutoBodyShop'       => __( 'Auto body shop', 'spokora-seo-toolkit' ),
			'AutoPartsStore'     => __( 'Auto parts store', 'spokora-seo-toolkit' ),
			'AutoRental'         => __( 'Auto rental', 'spokora-seo-toolkit' ),
			'AutoWash'           => __( 'Car wash', 'spokora-seo-toolkit' ),
			'GasStation'         => __( 'Gas station', 'spokora-seo-toolkit' ),
			'TireShop'           => __( 'Tire shop', 'spokora-seo-toolkit' ),
		),
		__( 'Education, government & community', 'spokora-seo-toolkit' ) => array(
			'EducationalOrganization' => __( 'Educational organization', 'spokora-seo-toolkit' ),
			'School'                  => __( 'School', 'spokora-seo-toolkit' ),
			'Preschool'               => __( 'Preschool', 'spokora-seo-toolkit' ),
			'ElementarySchool'        => __( 'Elementary school', 'spokora-seo-toolkit' ),
			'MiddleSchool'            => __( 'Middle school', 'spokora-seo-toolkit' ),
			'HighSchool'              => __( 'High school', 'spokora-seo-toolkit' ),
			'CollegeOrUniversity'     => __( 'College or university', 'spokora-seo-toolkit' ),
			'ChildCare'               => __( 'Child care / daycare', 'spokora-seo-toolkit' ),
			'Library'                 => __( 'Library', 'spokora-seo-toolkit' ),
			'NGO'                     => __( 'Nonprofit / NGO', 'spokora-seo-toolkit' ),
			'GovernmentOrganization'  => __( 'Government organization', 'spokora-seo-toolkit' ),
			'GovernmentOffice'        => __( 'Government office', 'spokora-seo-toolkit' ),
			'ReligiousOrganization'   => __( 'Religious organization', 'spokora-seo-toolkit' ),
			'Church'                  => __( 'Church', 'spokora-seo-toolkit' ),
			'Mosque'                  => __( 'Mosque', 'spokora-seo-toolkit' ),
			'Synagogue'               => __( 'Synagogue', 'spokora-seo-toolkit' ),
			'SportsOrganization'      => __( 'Sports organization / club', 'spokora-seo-toolkit' ),
		),
		__( 'Arts, media & entertainment', 'spokora-seo-toolkit' ) => array(
			'NewsMediaOrganization' => __( 'News / media organization', 'spokora-seo-toolkit' ),
			'RadioStation'          => __( 'Radio station', 'spokora-seo-toolkit' ),
			'TelevisionStation'     => __( 'Television station', 'spokora-seo-toolkit' ),
			'MovieTheater'          => __( 'Movie theater', 'spokora-seo-toolkit' ),
			'PerformingArtsTheater' => __( 'Performing arts theater', 'spokora-seo-toolkit' ),
			'ArtGallery'            => __( 'Art gallery', 'spokora-seo-toolkit' ),
			'Museum'                => __( 'Museum', 'spokora-seo-toolkit' ),
			'NightClub'             => __( 'Nightclub', 'spokora-seo-toolkit' ),
			'ComedyClub'            => __( 'Comedy club', 'spokora-seo-toolkit' ),
			'Casino'                => __( 'Casino', 'spokora-seo-toolkit' ),
			'AmusementPark'         => __( 'Amusement park', 'spokora-seo-toolkit' ),
			'Zoo'                   => __( 'Zoo', 'spokora-seo-toolkit' ),
			'BowlingAlley'          => __( 'Bowling alley', 'spokora-seo-toolkit' ),
			'GolfCourse'            => __( 'Golf course', 'spokora-seo-toolkit' ),
			'StadiumOrArena'        => __( 'Stadium or arena', 'spokora-seo-toolkit' ),
			'TouristAttraction'     => __( 'Tourist attraction', 'spokora-seo-toolkit' ),
			'EntertainmentBusiness' => __( 'Entertainment business (generic)', 'spokora-seo-toolkit' ),
		),
	);
}

/**
 * Flat list of allowed schema.org @type values.
 *
 * @return list<string>
 */
function spokora_schema_type_allowed_types() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$cache = array();
	foreach ( spokora_schema_type_choice_groups() as $types ) {
		foreach ( array_keys( $types ) as $type ) {
			$cache[] = $type;
		}
	}
	return $cache;
}

/**
 * Sanitize a submitted schema type; keep unknown stored values on save.
 *
 * @param string $type Raw type.
 * @param string $default Fallback when invalid.
 * @return string
 */
function spokora_sanitize_schema_type( $type, $default = 'ProfessionalService' ) {
	$type = sanitize_text_field( (string) $type );
	if ( in_array( $type, spokora_schema_type_allowed_types(), true ) ) {
		return $type;
	}
	if ( '' !== $type && preg_match( '/^[A-Za-z][A-Za-z0-9]*$/', $type ) ) {
		$old = get_option( 'spokora_options', array() );
		if ( is_array( $old ) && isset( $old['schema_type'] ) && (string) $old['schema_type'] === $type ) {
			return $type;
		}
	}
	return $default;
}

/**
 * Echo a grouped <select> for business / schema type.
 *
 * @param string               $selected Current @type.
 * @param array<string, mixed> $args name, id, class, style.
 */
function spokora_render_schema_type_select( $selected, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'name'        => 'schema_type',
			'id'          => 'schema_type',
			'class'       => '',
			'style'       => '',
			'show_saved'  => true,
		)
	);
	$selected = is_string( $selected ) ? $selected : '';
	if ( '' === $selected ) {
		$selected = 'ProfessionalService';
	}
	$allowed = spokora_schema_type_allowed_types();
	$extra   = ( $args['show_saved'] && '' !== $selected && ! in_array( $selected, $allowed, true ) );
	$name    = (string) $args['name'];
	$id      = (string) $args['id'];
	$class   = (string) $args['class'];
	$style   = (string) $args['style'];

	echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '"';
	if ( '' !== $class ) {
		echo ' class="' . esc_attr( $class ) . '"';
	}
	if ( '' !== $style ) {
		echo ' style="' . esc_attr( $style ) . '"';
	}
	echo '>';
	if ( $extra ) {
		echo '<option value="' . esc_attr( $selected ) . '" selected="selected">';
		echo esc_html(
			sprintf(
				/* translators: %s: schema.org type name */
				__( 'Saved type: %s', 'spokora-seo-toolkit' ),
				$selected
			)
		);
		echo '</option>';
	}
	foreach ( spokora_schema_type_choice_groups() as $group_label => $types ) {
		echo '<optgroup label="' . esc_attr( $group_label ) . '">';
		foreach ( $types as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $selected, $value, false ),
				esc_html( $label )
			);
		}
		echo '</optgroup>';
	}
	echo '</select>';
}
