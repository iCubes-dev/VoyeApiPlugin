<?php



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use Firebase\JWT\JWT;

//=================== login user ========================//

add_action('graphql_register_types', function () {
    register_graphql_mutation('loginWithMobile', [
        'inputFields' => [
            'email' => ['type' => 'String'],
        ],
        'outputFields' => [
            'status' => ['type' => 'Boolean'],
            'message' => ['type' => 'String'],
        ],
        'mutateAndGetPayload' => function ($input) {
            $input_value = sanitize_text_field($input['email']);
            $is_valid_email = filter_var($input_value, FILTER_VALIDATE_EMAIL);
            $is_valid_mobile = preg_match('/^[0-9]{10}$/', $input_value);

            try {
                if ($is_valid_email || $is_valid_mobile) {
                    $validation_response = validate($input_value);

                    if (!$validation_response['status']) {
                        throw new Exception($validation_response['message']);
                    }

                    $otp = generateAndSendOTP($input_value);

                    if (!empty($otp)) {
                        return [
                            'status' => true,
                            'message' => 'OTP has been sent successfully.',
                        ];
                    } else {
                        throw new Exception('Failed to generate or send OTP. Please try again later.');
                    }
                } else {
                    throw new Exception('Invalid email or mobile number format.');
                }
            } catch (Exception $e) {
                return [
                    'status' => false,
                    'message' => $e->getMessage(),
                ];
            }

        },
    ]);
});

//=================== Verify user ========================//

add_action('graphql_register_types', function () {
    register_graphql_mutation('verifyOtp', [
        'inputFields' => [
            'email' => [
                'type' => 'String',
                'description' => 'The email address of the user.',
            ],
            'redirect' => [
                'type' => 'String',
                'description' => 'The URL to redirect after successful verification.',
            ],
            'otp' => [
                'type' => 'String',
                'description' => 'The 6-digit OTP code.',
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the OTP was successfully verified.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A message describing the result.',
            ],
            'returnUrl' => [
                'type' => 'String',
                'description' => 'The redirect URL on success.',
            ],
            'token' => ['type' => 'String'],
        ],
        'mutateAndGetPayload' => function ($input, $context, $info) {
            
            $email = sanitize_email($input['email']);
            $redirect = sanitize_text_field($input['redirect']);
            $otp = sanitize_text_field($input['otp']);
            $token = '';

            // Check if user exists with the mobile number
            $user = get_user_by('email', $email);

            if (!$user) {
                return [
                    'token' => null,
                    'status' => false,
                    'message' => 'User does not exist.',
                ];
            }
            
            $otp_digits = str_split($otp);
            for ($i = 1; $i <= 6; $i++) {
                $_POST['digit-' . $i] = $otp_digits[$i - 1] ?? '';
            }
            $_POST['email'] = $email;
            $_POST['redirect'] = $redirect;

            if (class_exists('WooCommerce') && isset( WC()->session) ) {
                // $custom_data = WC()->session->get('user_otp');
                $results = verify_user_otp_direct($_POST);
                if($results['success'] == 1 && $results['success'] == 'Success'){
                    $token = generate_mobile_login_token( $user );
                }
            }

            return [
                'success' => $results['success'] ?? false,
                'message' => $results['message'] ?? __('Error occurred.'),
                'token' => $token ?? null,
                'returnUrl' => $results['returnUrl'] ?? null,
            ];
        },
    ]);
});

// ================== resend otp =========================//

add_action('graphql_register_types', function () {
    register_graphql_mutation('resendOtp', [
        'inputFields' => [
            'email' => ['type' => 'String'],
        ],
        'outputFields' => [
            'status' => ['type' => 'Boolean'],
            'message' => ['type' => 'String'],
        ],
        'mutateAndGetPayload' => function ($input) {
            $input_value = sanitize_text_field($input['email']);
            $is_valid_email = filter_var($input_value, FILTER_VALIDATE_EMAIL);
            $is_valid_mobile = preg_match('/^[0-9]{10}$/', $input_value);

            try {
                if ($is_valid_email || $is_valid_mobile) {
                    $validation_response = validate($input_value);

                    if (!$validation_response['status']) {
                        throw new Exception($validation_response['message']);
                    }

                    $otp = generateAndSendOTP($input_value);

                    if (!empty($otp)) {
                        return [
                            'status' => true,
                            'message' => 'OTP has been sent successfully.',
                        ];
                    } else {
                        throw new Exception('Failed to generate or send OTP. Please try again later.');
                    }
                } else {
                    throw new Exception('Invalid email or mobile number format.');
                }
            } catch (Exception $e) {
                return [
                    'status' => false,
                    'message' => $e->getMessage(),
                ];
            }

        },
    ]);
});


// ============ getCurrent User details ================//

add_action('graphql_register_types', 'registerProfileQuery');

function registerProfileQuery() {
    // Register the Profile type
    register_graphql_object_type('UserProfile', [
        'description' => 'Details of the user profile',
        'fields' => [
            'userId' => [
                'type' => 'ID',
                'description' => 'The ID of the user',
            ],
            'username' => [
                'type' => 'String',
                'description' => 'The username of the user',
            ],
            'email' => [
                'type' => 'String',
                'description' => 'The email address of the user',
            ],
            'displayName' => [
                'type' => 'String',
                'description' => 'The display name of the user',
            ],
            'phone' => [
                'type' => 'String',
                'description' => 'The phone number of the user',
            ],
            'phoneCode' => [
                'type' => 'String',
                'description' => 'The phone code of the user',
            ],
            'avtar' => [
                'type' => 'String',
                'description' => 'The avtar details of the user',
            ],
        ],
    ]);

    // Register the overall response type for Profile
    register_graphql_object_type('ProfileResponse', [
        'description' => 'Response structure for the user profile',
        'fields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the operation was successful',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'Additional information about the response',
            ],
            'profile' => [
                'type' => 'UserProfile',
                'description' => 'The profile details of the user',
            ],
        ],
    ]);

    // Register the query for fetching profile
    register_graphql_field('RootQuery', 'getProfileByUser', [
        'type' => 'ProfileResponse',
        'description' => 'Fetch the profile of the current user',
        'resolve' => function () {
            $user_id = get_current_user_id();

            if (!$user_id || $user_id == 0) {
                return [
                    'success' => false,
                    'message' => 'Please authenticate first',
                    'profile' => null,
                ];
            }

            $user = get_userdata($user_id);
            $phone = get_user_meta($user->ID, 'billing_phone', true);
            $phone_code = get_user_meta($user->ID, 'phone_code', true);
            $avtar = esc_url( get_avatar_url( $user_id ) );

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'profile' => null,
                ];
            }

            return [
                'success' => true,
                'message' => 'User profile retrieved successfully',
                'profile' => [
                    'userId' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'displayName' => $user->display_name,
                    'phone' => $phone,
                    'phoneCode' => $phone_code, 
                    'avtar' => $avtar, 
                ],
            ];
        },
    ]);
}

// ============== customize pre define query  =====================//

add_action('graphql_register_types', function () {
    register_graphql_field('Customer', 'phone', [
        'type' => 'String',
        'description' => __('The billing phone number of the customer', 'voye'),
        'resolve' => function ($customer, $args, $context, $info) {
            return get_user_meta($customer->ID, 'billing_phone', true);
        },
    ]);

    register_graphql_field('Customer', 'phoneCode', [
        'type' => 'String',
        'description' => __('The phone code of the customer', 'voye'),
        'resolve' => function ($customer, $args, $context, $info) {
            return get_user_meta($customer->ID, 'phone_code', true);
        },
    ]);

    register_graphql_field('Customer', 'avatar', [
        'type' => 'String',
        'description' => __('The avatar URL of the customer', 'voye'),
        'resolve' => function ($customer, $args, $context, $info) {
            return esc_url(get_avatar_url($customer->ID));
        },
    ]);
});


add_action('graphql_register_types', function () {
    register_graphql_object_type('PaymentMethod', [
        'description' => 'Details of a saved payment method',
        'fields' => [
            'id' => [
                'type' => 'ID',
                'description' => 'The ID of the payment method',
            ],
            'cardType' => [
                'type' => 'String',
                'description' => 'The type of card (e.g., Visa, MasterCard)',
            ],
            'expiryMonth' => [
                'type' => 'Int',
                'description' => 'The expiration month of the card',
            ],
            'expiryYear' => [
                'type' => 'Int',
                'description' => 'The expiration year of the card',
            ],
        ],
    ]);
});


add_action('graphql_register_types', function () {
    register_graphql_mutation('addPaymentMethod', [
        'inputFields' => [
            'clientMutationId' => [
                'type' => 'String',
                'description' => 'A unique identifier for the mutation',
            ],
            'customerId' => [
                'type' => 'ID',
                'description' => 'The ID of the customer',
            ],
            'cardType' => [
                'type' => 'String',
                'description' => 'The type of the card',
            ],
            'last4' => [
                'type' => 'String',
                'description' => 'The last four digits of the card number',
            ],
            'cardNumber' => [
                'type' => 'String',
                'description' => 'The full card number (securely used for tokenization)',
            ],
            'cvc' => [
                'type' => 'String',
                'description' => 'The CVC of the card',
            ],
            'expiryMonth' => [
                'type' => 'Int',
                'description' => 'The expiration month of the card',
            ],
            'expiryYear' => [
                'type' => 'Int',
                'description' => 'The expiration year of the card',
            ],
        ],
        'outputFields' => [
            'paymentMethod' => [
                'type' => 'PaymentMethod',
                'description' => 'The payment method object',
            ],
            'clientMutationId' => [
                'type' => 'String',
                'description' => 'The unique identifier for the mutation',
            ],
        ],
        'mutateAndGetPayload' => function ($input, $context, $info) {

            if (!class_exists('Stripe\Stripe')) {
                include_once WC_STRIPE_PLUGIN_PATH . '/vendor/autoload.php';
            }

            $customer_id = absint($input['customerId']);
            $card_type = sanitize_text_field($input['cardType']);
            $last4 = sanitize_text_field($input['last4']);
            $card_number = sanitize_text_field($input['cardNumber']);
            $cvc = sanitize_text_field($input['cvc']);
            $expiry_month = absint($input['expiryMonth']);
            $expiry_year = absint($input['expiryYear']);

            if (empty($customer_id) || empty($card_number) || empty($cvc) || empty($expiry_month) || empty($expiry_year)) {
                throw new \GraphQL\Error\UserError('Missing required fields.');
            }

           

           
            $tokens = WC_Payment_Tokens::get_order_tokens( 32821 );
            // $order = wc_get_order( 15257 );
            // // $tokens =  $order->get_payment_tokens();
            print_r($tokens);
            die('stop1');
            $token = new WC_Payment_Token_CC();
            $token->set_user_id($customer_id);
            $token->set_token( 'token here' );
            $token->set_last4( '4242' );
            $token->set_expiry_year( '2027' );
            $token->set_expiry_month( '1' ); // incorrect length
            $token->set_card_type( 'visa' );
            var_dump( $token->validate() ); // bool(false)
            $token->set_expiry_month( '01' );
            var_dump( $token->validate() ); // bool(true)
            
              

            if ($token->validate()) {
                $token->save();
                return [
                    'paymentMethod' => [
                        'id' => $token->get_id(),
                        'cardType' => $token->get_card_type(),
                        'expiryMonth' => $token->get_expiry_month(),
                        'expiryYear' => $token->get_expiry_year(),
                    ],
                    'clientMutationId' => $input['clientMutationId'],
                ];
            } else {
                throw new \GraphQL\Error\UserError('Invalid or missing payment token fields.');
            }
        },
    ]);
});



//============================ delete account ====================================// 

add_action('graphql_register_types', function () {
    register_graphql_mutation('sendEmail', [
        'inputFields' => [
            'email' => [
                'type' => 'String',
                'description' => __('The email address of the sender', 'voye'),
            ],
            'reason' => [
                'type' => 'String',
                'description' => __('The reason for contacting', 'voye'),
            ],
            'message' => [
                'type' => 'String',
                'description' => __('The message content', 'voye'),
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => __('Indicates if the email was sent successfully', 'voye'),
            ],
            'message' => [
                'type' => 'String',
                'description' => __('A message about the result of the operation', 'voye'),
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $email = sanitize_email($input['email']);
            $reason = sanitize_text_field($input['reason']);
            $message_content = sanitize_textarea_field($input['message']);

            if (empty($email) || !is_email($email)) {
                return [
                    'success' => false,
                    'message' => __('Invalid email address.', 'voye'),
                ];
            }

            $subject = sprintf(__('Contact Form Submission: %s', 'voye'), $reason);
            $body = sprintf(
                __("Message from: %s\n\nReason: %s\n\nMessage: %s\n\nWebsite Name: %s\nWebsite URL: %s", 'voye'),
                $email,
                $reason,
                $message_content,
                'Voye', 
                'voye.com' 
            );
            $headers = ['Content-Type: text/plain; charset=UTF-8'];

            $email_sent = wp_mail(get_option('admin_email'), $subject, $body, $headers);

            if ($email_sent) {
                return [
                    'success' => true,
                    'message' => __('Email sent successfully.', 'voye'),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => __('Failed to send email. Please try again later.', 'voye'),
                ];
            }
        },
    ]);
});




//=================== //function ========================//


function validate($input_value){

    if (filter_var($input_value, FILTER_VALIDATE_EMAIL)) {
        
        $user = get_user_by('email', $input_value);
        if ($user) {
            $response = [
                'status' => true,
                'message' => 'User found with email.',
            ];
        } else {
            $response['message'] = 'No user found with this email.';
        }
    } elseif (preg_match('/^[0-9]{10}$/', $input_value)) {
        $user = get_user_by('meta_value', $input_value); 
        if ($user) {
            $response = [
                'status' => true,
                'message' => 'User found with mobile number.',
            ];
        } else {
            $response['message'] = 'No user found with this mobile number.';
        }
    } else {
        $response['message'] = 'Invalid email or mobile number format.';
    }

    return $response;
}

function generateAndSendOTP($email) {
    $otp_code = mt_rand(111111, 999999);
    $transient_key = 'user_otp_' . md5($email);

    set_transient(
        $transient_key,
        array(
            'user_email' => $email,
            'code'       => $otp_code,
        ),
        10 * MINUTE_IN_SECONDS // 10-minute expiration
    );

    // Prepare the email content
    $subject = __('Voye OTP (One Time Password)', 'voye');
    $heading = __('Voye OTP (One Time Password)', 'voye');
    $message = '<p>' . __('Hi, Please use the following OTP (One Time Password) below:', 'voye') . '</p>
    <div style="font-weight:bold">' . $otp_code . '</div>
    <br>' . get_field('otp_email_text', 'option');

    // Send the OTP email
    send_wc_mail($email, $subject, $heading, $message);
    return $otp_code;
}


function verify_user_otp_direct($data) {
    $results = [
        'success' => false,
        'message' => __('General Error.'),
    ];

    $digit1 = sanitize_text_field($data['digit-1']);
    $digit2 = sanitize_text_field($data['digit-2']);
    $digit3 = sanitize_text_field($data['digit-3']);
    $digit4 = sanitize_text_field($data['digit-4']);
    $digit5 = sanitize_text_field($data['digit-5']);
    $digit6 = sanitize_text_field($data['digit-6']);

    $user_otp = $digit1 . $digit2 . $digit3 . $digit4 . $digit5 . $digit6;

    $email = sanitize_email($data['email']);
    $redirect = sanitize_text_field($data['redirect']);

    if ($email && is_email($email)) {
        
        $transient_key = 'user_otp_' . md5($email);
        $user_otp_data = get_transient($transient_key);

        if ($user_otp && strlen($user_otp) === 6) {
            if ($user_otp_data) {
                if ($user_otp_data['code'] === (int)$user_otp && $user_otp_data['user_email'] === $email) {
                    $user_check = get_user_by('email', $email);

                    if ($user_check && $user_check->ID) {
                        wp_set_current_user($user_check->ID, $user_check->data->user_login);
                        wp_set_auth_cookie($user_check->ID);
                        do_action('wp_login', $user_check->data->user_login, $user_check);

                        // Clear transient after successful OTP verification
                        delete_transient($transient_key);

                        $results = [
                            'success' => true,
                            'message' => __('Success.'),
                            'returnUrl' => $redirect ? $redirect : get_permalink(get_option('woocommerce_myaccount_page_id')),
                        ];
                    } else {
                        $results['message'] = __("Can't find user.");
                    }
                } else {
                    // Handle OTP attempts using a separate transient
                    $attempts_key = 'otp_attempts_' . md5($email);
                    $attempts = get_transient($attempts_key) ?? 0;

                    $attempts++;
                    set_transient($attempts_key, $attempts, 10 * MINUTE_IN_SECONDS);

                    if ($attempts >= 3) {
                        $results['message'] = __('Maximum attempts reached. Please try again later.');
                    } else {
                        $results['message'] = __('OTP Code Invalid.');
                        $results['attempts'] = $attempts;
                    }
                }
            } else {
                $results['message'] = __('OTP expired or not found.');
            }
        } else {
            $results['message'] = __('Invalid OTP.');
        }
    } else {
        $results['message'] = __('Invalid Email.');
    }

    return $results;
}


// ================= token ========================//

function generate_mobile_login_token( $user ) {
    $not_before = time(); 
    $expiration = time() + ( DAY_IN_SECONDS );

    $token = [
        'iss'  => get_bloginfo( 'url' ), // The issuer (site URL)
        'iat'  => time(),                // Issued at (current timestamp)
        'nbf'  => $not_before,           // Not valid before (current timestamp)
        'exp'  => $expiration,           // Expiration time
        'data' => [
            'user' => [
                'id' => $user->ID,      
            ],
        ],
    ];

    $token = apply_filters( 'graphql_jwt_auth_token_before_sign', $token, $user );
    JWT::$leeway = 60;

    $jwt_token = JWT::encode( $token, get_graphql_secret_key(), 'HS256' );

    return $jwt_token;
}

function get_graphql_secret_key() {
    $secret_key = defined( 'GRAPHQL_JWT_AUTH_SECRET_KEY' ) && ! empty( GRAPHQL_JWT_AUTH_SECRET_KEY )
        ? GRAPHQL_JWT_AUTH_SECRET_KEY
        : null;

    return apply_filters( 'graphql_jwt_auth_secret_key', $secret_key );
}




add_action('wp_head',function(){
    // $user_check = get_user_by('email', 'sagi@voyeglobal.com');
    // wp_set_current_user($user_check->ID, $user_check->data->user_login);
    //                     wp_set_auth_cookie($user_check->ID);
    //                     do_action('wp_login', $user_check->data->user_login, $user_check);
    // $user_email = 'pankaj@gmail.com';
    // $new_password = 'QvZnT)5StdhgJY)Z';
    //  $username = 'pankaj.sharma-1505' ;
    // $user = get_user_by('email', $user_email);

    // if ($user && $user->ID) {
    //     // Set the new password
    //     wp_set_password($new_password, $user->ID);

    //     // Optional: Output a success message for debugging
    //     echo 'Password changed successfully for user: ' . $user_email;
    // } else {
    //     // Handle case if the user is not found
    //     echo 'User not found for email: ' . $user_email;
    // }
});




// add_action('wp_footer',function(){
//     global $woocommerce;
//     $woocommerce->cart->add_to_cart( 1839, 1 );  
// });