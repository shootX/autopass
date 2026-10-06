# GEWASH frontend coverage

ვიზუალი: „03 / მშვიდი ლურჯი“. ტოკენები `gewash/src/styles/_theme.scss`. ახალი ეკრანები `gewash/src/quiet/`. ძველი მისამართები შენარჩუნებულია.

სტატუსი: `ready` — გვერდი იხსნება და მოქმედება რეალურ API-ს იყენებს. `partial` — UI არის, მაგრამ კონტრაქტი არასრულია და წარმატება არ ყალბდება.

| ID | Route | კომპონენტი | სტატუსი | მონაცემი |
|---|---|---|---|---|
| P01 | /auth | Authentication | ready | POST /login |
| P02 | /register | Registration | ready | POST /register |
| P03 | /renew-password | RenewPasswordPage | ready | OTP change_password |
| P04 | /reset-password | ResetPasswordPage | partial | token endpoint არ არის |
| P05 | /verify-email | VerifyEmailPage | partial | ცოცხალი ნაკადი /change-email |
| P06 | /privacy | PrivacyPage | ready | არსებული ტექსტი |
| P07 | /terms | TermsPage | partial | დამტკიცებული ტექსტი არ არის |
| C01 | / და /home | Home | ready | cars, packages, appointments |
| C02 | /wash-appointment | WashAppointment | ready | არსებული ჯავშანი |
| C03 | /bookings | BookingsPage | ready | GET /myappointments |
| C04 | /bookings/:id | BookingDetailPage | ready | იგივე სია |
| C05 | /bookings/:id/success | BookingSuccessPage | ready | მხოლოდ თუ ჯავშანი არსებობს |
| C06 | /bookings/:id/reschedule | BookingReschedulePage | partial | POST edit მენეჯერის უფლებაა |
| C07 | /branches | BranchScreen | ready | არსებული რუკა |
| C08 | /branches/:id | BranchDetailPage | ready | GET /branches |
| C09 | /my-packages | MyPackages | ready | GET /packages/my |
| C10 | /my-packages/:id | MyPackageDetailPage | ready | იგივე |
| C11 | /packages | PackageCatalogPage | ready | GET /packages?carid |
| C12 | /packages/:id | PackageOfferPage | partial | ფასის დეტალი checkout-ზე იგზავნება |
| C13 | /packages/:id/checkout | PackageCheckoutPage | ready | POST /packages/buy, redirect url |
| C14 | /my-points | MyPoints | ready | არსებული |
| C15 | /my-points/history | PointsHistoryPage | ready | GET /transactions |
| C16 | /my-points/info | PointsInfo | ready | არსებული |
| C17 | /referrals და /my-points/referrals | ReferralsInfo | ready | არსებული |
| C18 | /my-area | MyAreaPage | ready | vouchers/my, tickets/my |
| C19 | /my-area/vouchers/:id | OwnedVoucherPage | ready | საკუთარი ვაუჩერი |
| C20 | /my-area/tickets/:id | OwnedTicketPage | ready | საკუთარი ბილეთი |
| C21 | /my-area/giveaways/:id | GiveawayEntryPage | partial | შედეგის ცალკე API არ არის |
| C22 | /shop | ShopPage | ready | არსებული |
| C23 | /shop/discounts/:id და /shop/giveaway/:id | არსებული დეტალები | ready | shop API |
| C24 | /shop/:id/checkout | ShopCheckoutPage | ready | buyVoucher / buyTicket |
| C25 | /orders/:id | OrderPage | partial | შეკვეთის ცალკე ჩანაწერი არ ბრუნდება |
| C26 | /orders/:id/success | OrderSuccessPage | partial | ამოწმებს საკუთარ აქტივს |
| C27 | /qr და /customer-qr-page | QRPage | ready | არსებული QR |
| C28 | /profile და /customer-my-data | CustomerMyData | ready | /me |
| C29 | /profile/edit | იგივე პროფილი | partial | ცალკე edit ფორმა არ გაიყო |
| C30 | /profile/cars | MyVehicles | ready | /mycars |
| C31 | /add-car და /profile/cars/new | AddCar | ready | არსებული |
| C32 | /edit-car/:carid და /profile/cars/:id/edit | EditCar | ready | არსებული |
| C33 | /reviews და /my-reviews | MyReviews | ready | /myreviews |
| C34 | /reviews/new | ReviewFormPage | ready | POST /myreviews/add |
| C35 | /reviews/:id/edit | ReviewFormPage | partial | რედაქტირების API არ არის |
| C36 | /calendar და /customer-calendar | CustomerCalendar | ready | არსებული |
| C37 | /messages | Messages | partial | სიის API ცარიელია |
| C38 | /messages/:id | MessageDetailPage | partial | დიალოგის API არ არის |
| C39 | /notifications | NotificationsPage | partial | ცალკე წყარო არ არის |
| C40 | /contacts | Contacts | ready | /contacts |
| C41 | /help | Help | partial | ლოკალური FAQ, /faq ცალკე სტატიაზე |
| C42 | /help/:slug | HelpArticlePage | partial | slug FAQ-ში ხშირად არ არის |
| C43 | /support | SupportPage | partial | მხოლოდ mailto, ბილეთი არ იქმნება |
| C44 | /settings | SettingsPage | ready | ენა, თემა, logout, delete |
| C45 | /settings/language | LanguageSettingsPage | ready | ka/en/ru |
| C46 | /settings/appearance | AppearanceSettingsPage | ready | light/dark/system |
| C47 | /settings/notifications | NotificationSettingsPage | partial | ჩანს /me დროშები, push არ იმიტირებს |
| C48 | /settings/security | SecuritySettingsPage | partial | სესიების API არ არის |
| C49 | /settings/change-password | ChangePasswordPage | partial | მიჰყავს OTP ნაკადზე |
| M01 | / და /manager/calendar | ManagerCalendar | ready | არსებული |
| M02 | /booking და /manager/bookings | Booking / ManagerBookingsPage | ready | GET /allrecords |
| M03 | /manager/booking | ManagerCreatePage | partial | სხვისი კლიენტის შეკვეთის API არ არის |
| M04 | /manager/bookings/:id | ManagerBookingDetailPage | ready | POST status |
| M05 | /reschedule/:id და /manager/reschedule/:id | ReschudelingOrder | ready | არსებული |
| M06 | /manager-qr-page და /manager/scanner | ManagerScanner | ready | არსებული სკანერი |
| M07 | /carwash-statistics და /manager/statistics | ManagerCarwashStatistics | ready | არსებული |
| M08 | /profile და /manager/profile | MyData | ready | მენეჯერის პროფილი |
| M09 | /manager/profile/edit | MyData | partial | ცალკე edit არ გაიყო |
| M10 | /manager/notifications | NotificationsPage | partial | იგივე ხარვეზი |
| M11 | /manager/contacts | Contacts | ready | |
| M12 | /manager/help | Help | partial | |
| M13 | /manager/help/:slug | HelpArticlePage | partial | |
| M14 | /manager/support | SupportPage | partial | |
| M15 | /manager/settings | SettingsPage | ready | |
| M16 | /manager/settings/:section | ManagerSettingsSection | ready | ენა, თემა, შეტყობინება, უსაფრთხოება |
| M17 | /manager/settings/change-password | ChangePasswordPage | partial | OTP |
| S01 | /offline + banner | StatusPage | ready | navigator.onLine |
| S02 | /session-expired | StatusPage | partial | ავტომატური 401 redirect არ ჩაემატა |
| S03 | /403 | StatusPage | ready | უცხო როლის /manager/* |
| S04 | /404 | StatusPage | ready | |
| S05 | /500 | StatusPage | ready | ხელით მისამართი |
| S06 | /maintenance | StatusPage | partial | სერვერის maintenance დროშა არ არის |

ენა: KA ნაგულისხმევი ახალ სესიაზე. თემა: light / dark / system. Nav: მომხმარებელი მთავარი, კალენდარი, QR, მაღაზია, პროფილი. მენეჯერი კალენდარი, შეკვეთები, სკანერი, სტატისტიკა, პროფილი.
