var gulp = require('gulp');
var wpPot = require('gulp-wp-pot');

gulp.task('default', function () {
    return gulp.src([
            '*.php',
            'includes/admin/views/*.php',
            'includes/views/*.php',
            'includes/*.php'
        ])
        .pipe(wpPot( {
            domain: 'woocommerce-trxservices',
            destFile:'woocommerce-trxservices.pot',
            package: 'WC_TrxServices',
            bugReport: 'https://hitfactory.co.nz'
        } ))
        .pipe(gulp.dest('languages/woocommerce-trxservices.pot'));
});
