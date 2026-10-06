# GEWASH frontend integration gaps

Live რეჟიმი ნაგულისხმევია. `VITE_DEMO` არ ირთვება და ყალბი წარმატება არ იგზავნება.

| ოპერაცია | Endpoint | UI | მდგომარეობა |
|---|---|---|---|
| შესვლა/რეგისტრაცია/OTP | /login, /register, /register/verify | P01–P03, P05 | ready |
| პაროლი token-ით | არ არის | /reset-password | missing. ცოცხალი გზაა /change_password OTP |
| პირობების ტექსტი | არ არის | /terms | missing. /privacy არსებობს |
| ჩემი ჯავშნები | GET /myappointments | /bookings | ready |
| ჯავშნის წაშლა | DELETE /appointments/:id/remove | დეტალი | ready, თუ სერვერი დაუშვებს |
| მომხმარებლის გადატანა | POST /appointments/:id/edit | /bookings/:id/reschedule | missing უფლება: კონტროლერი მხოლოდ მენეჯერს უშვებს |
| პაკეტის ყიდვა | POST /packages/buy | checkout | ready, თუ FLITT აბრუნებს url-ს. url-ის გარეშე პაკეტი არ აქტიურდება UI-დან |
| ქულებით ყიდვა | POST /vouchers/buy, /tickets/buy | /shop/:id/checkout | ready |
| შეკვეთის ჩანაწერი | არ არის | /orders/:id | missing |
| გათამაშების შედეგი | არ არის | /my-area/giveaways/:id | missing |
| შეტყობინებები/დიალოგი | არ არის | /messages | missing |
| მხარდაჭერის ბილეთი | არ არის | /support | missing. მხოლოდ mailto |
| მენეჯერის შეკვეთა სხვის კლიენტზე | არ არის | /manager/booking | missing. /appointments/add იწერს ავტორიზებულ მომხმარებელს |
| ანგარიშის წაშლა | POST /account/delete | პარამეტრები | ready |
| სესიის 401 | — | /session-expired | გვერდი არის, გლობალური redirect არ დაემატა |
| maintenance | — | /maintenance | სერვერის დროშა არ არის |

დროის ზონა ჯავშნებზე რჩება სერვერის `date` + `time` ველებზე. ბრაუზერის timezone-ით დღე არ გადაითვლება.
