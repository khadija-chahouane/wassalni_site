<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'fr', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang = $_SESSION['lang'] ?? 'ar';

$translations = [
    'ar' => [
        // عام
        'brand' => '🚗 وصلني - Wasalni',
        'welcome' => 'مرحباً',
        'logout' => 'خروج',
        'back' => 'العودة',
        
        // صفحة auth.php
        'login_title' => 'تسجيل الدخول',
        'signup_title' => 'إنشاء حساب جديد',
        'fullname' => 'الاسم الكامل',
        'email' => 'البريد الإلكتروني',
        'cin' => 'رقم البطاقة الوطنية (CIN)',
        'phone' => 'رقم الهاتف',
        'password' => 'كلمة السر',
        'gender' => 'اختاري الجنس',
        'male' => 'ذكر',
        'female' => 'أنثى',
        'role' => 'نوع الحساب',
        'client_role' => 'زبون (Client)',
        'driver_role' => 'سائق (Chauffeur)',
        'btn_signin' => 'تسجيل الدخول',
        'btn_signup' => 'إنشاء الحساب',
        'driver_box_title' => 'معلومات السيارة والسائق:',
        'marque' => 'ماركة السيارة (مثلاً: Dacia)',
        'model' => 'موديل السيارة (مثلاً: Logan)',
        'matricule' => 'رقم اللوحة (Matricule)',
        'dep_city' => 'مدينة الانطلاق',
        'arr_city' => 'مدينة الوصول',
        'price_seat' => 'ثمن المقعد (درهم)',
        'seats_num' => 'عدد المقاعد المتوفرة',

        // صفحة dashboard.php (الزبون)
        'title_route' => '📍 حدد مسارك (داخل أو بين المدن)',
        'depart' => 'نقطة الانطلاق (حي أو شارع):',
        'arrivee' => 'الوجهة:',
        'distance' => 'المسافة المحسوبة (العداد):',
        'suggested_fare' => 'الثمن المقترح (الافتراضي):',
        'custom_price' => '💰 اقترح ثمنك الخاص (التفاوض - InDrive Style):',
        'gender_pref' => '🛡️ تفضيل نوع السائق (الأمان):',
        'same_gender' => 'الرغبة في السفر مع نفس الجنس فقط',
        'any_gender' => 'الكل (بدون تفضيل)',
        'btn_request' => '🚀 اطلب الرحلة وارسِل السعر',
        'success_msg' => '✅ تم ارسال طلبك بنجاح! في انتظار قبول السائق المناسب.',

        // صفحة driver_dashboard.php (السائق)
        'driver_portal' => 'بوابة السائقين',
        'online_status' => '● متصل (Online)',
        'current_ride' => 'الرحلة الحالية (Current Ride)',
        'end_trip' => 'إنهاء الرحلة',
        'new_requests' => 'طلبات جديدة (New Ride Requests)',
        'accept' => 'قبول (Accept)',
        'decline' => 'رفض (Decline)',
        'earnings' => "مكاسب اليوم (Today's Earnings)",
        'no_requests' => 'لا توجد طلبات جديدة حالياً في هذه المنطقة.'
    ],
    'fr' => [
        'brand' => '🚗 Wasalni',
        'welcome' => 'Bienvenue',
        'logout' => 'Déconnexion',
        'back' => 'Retour',
        
        'login_title' => 'Connexion',
        'signup_title' => 'Créer un compte',
        'fullname' => 'Nom complet',
        'email' => 'E-mail',
        'cin' => 'CIN',
        'phone' => 'Téléphone',
        'password' => 'Mot de passe',
        'gender' => 'Genre',
        'male' => 'Homme',
        'female' => 'Femme',
        'role' => 'Type de compte',
        'client_role' => 'Client',
        'driver_role' => 'Chauffeur',
        'btn_signin' => 'Se connecter',
        'btn_signup' => "S'inscrire",
        'driver_box_title' => 'Informations du véhicule :',
        'marque' => 'Marque (ex: Dacia)',
        'model' => 'Modèle (ex: Logan)',
        'matricule' => 'Matricule',
        'dep_city' => 'Ville de départ',
        'arr_city' => "Ville d'arrivée",
        'price_seat' => 'Prix par place (MAD)',
        'seats_num' => 'Places disponibles',

        'title_route' => '📍 Définissez votre trajet',
        'depart' => 'Point de départ :',
        'arrivee' => 'Destination :',
        'distance' => 'Distance estimée :',
        'suggested_fare' => 'Prix suggéré :',
        'custom_price' => '💰 Proposez votre prix (InDrive Style) :',
        'gender_pref' => '🛡️ Préférence du genre :',
        'same_gender' => 'Voyager avec le même sexe uniquement',
        'any_gender' => 'Indifférent (Tous)',
        'btn_request' => '🚀 Demander la course',
        'success_msg' => '✅ Demande envoyée avec succès !',

        'driver_portal' => 'Portail Chauffeur',
        'online_status' => '● En ligne',
        'current_ride' => 'Course Actuelle',
        'end_trip' => 'Terminer',
        'new_requests' => 'Nouvelles Demandes',
        'accept' => 'Accepter',
        'decline' => 'Refuser',
        'earnings' => "Gains du jour",
        'no_requests' => 'Aucune nouvelle demande.'
    ],
    'en' => [
        'brand' => '🚗 Wasalni',
        'welcome' => 'Welcome',
        'logout' => 'Logout',
        'back' => 'Back',
        
        'login_title' => 'Sign In',
        'signup_title' => 'Create Account',
        'fullname' => 'Full Name',
        'email' => 'Email',
        'cin' => 'CIN',
        'phone' => 'Phone',
        'password' => 'Password',
        'gender' => 'Gender',
        'male' => 'Male',
        'female' => 'Female',
        'role' => 'Account Type',
        'client_role' => 'Client',
        'driver_role' => 'Chauffeur',
        'btn_signin' => 'Sign In',
        'btn_signup' => 'Sign Up',
        'driver_box_title' => 'Vehicle Information:',
        'marque' => 'Marque (e.g. Dacia)',
        'model' => 'Model (e.g. Logan)',
        'matricule' => 'Plate Number',
        'dep_city' => 'Departure City',
        'arr_city' => 'Arrival City',
        'price_seat' => 'Seat Price (MAD)',
        'seats_num' => 'Available Seats',

        'title_route' => '📍 Set Your Route',
        'depart' => 'Pickup Location:',
        'arrivee' => 'Destination:',
        'distance' => 'Estimated Distance:',
        'suggested_fare' => 'Suggested Fare:',
        'custom_price' => '💰 Propose Your Price (InDrive Style):',
        'gender_pref' => '🛡️ Gender Preference:',
        'same_gender' => 'Travel with the same gender only',
        'any_gender' => 'Any (No preference)',
        'btn_request' => '🚀 Request Ride',
        'success_msg' => '✅ Ride request sent successfully!',

        'driver_portal' => 'Chauffeur Portal',
        'online_status' => '● Online',
        'current_ride' => 'Current Ride',
        'end_trip' => 'End Trip',
        'new_requests' => 'New Ride Requests',
        'accept' => 'Accept',
        'decline' => 'Decline',
        'earnings' => "Today's Earnings",
        'no_requests' => 'No new requests available.'
    ]
];

function t($key) {
    global $translations, $lang;
    return $translations[$lang][$key] ?? $key;
}
?>