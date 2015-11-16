var gulp = require('gulp');
var wpPot = require('gulp-wp-pot');
var sort = require('gulp-sort');

gulp.task('default', function () {
    return gulp.src([
            '*.php',
            'includes/admin/views/*.php',
            'includes/views/*.php',
            'includes/*.php'
        ])
        .pipe(sort())
        .pipe(wpPot( {
            domain: 'woocommerce-trxservices',
            destFile:'woocommerce-trxservices.pot',
            package: 'WC_TrxServices',
            bugReport: 'http://hitfactory.co.nz'
        } ))
        .pipe(gulp.dest('languages'));
});