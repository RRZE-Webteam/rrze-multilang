'use strict';

const
    { src, dest, watch, series } = require('gulp'),
    sass = require('gulp-sass'),
    cleancss = require('gulp-clean-css'),
    postcss = require('gulp-postcss'),
    autoprefixer = require('autoprefixer'),
    uglify = require('gulp-uglify'),
    babel = require('gulp-babel'),
    bump = require('gulp-bump'),
    semver = require('semver'),
    info = require('./package.json'),
    rename = require('gulp-rename'),
    touch = require('gulp-touch-cmd');

function css() {
    return src(info.source.sass + 'rrze-multilang.scss', {
            sourcemaps: false
        })
        .pipe(sass())
        .pipe(postcss([autoprefixer()]))
        .pipe(cleancss())
	.pipe(rename(info.name + '.css'))
	.pipe(dest(info.target.sass))
	.pipe(touch());
}




function js() {
    return src([info.source.js + '*.js', '!./assets/js/**/*.min.js'])
        .pipe(uglify())
        .pipe(dest(info.target.js))
	.pipe(touch());
}

function patchPackageVersion() {
    var newVer = semver.inc(info.version, 'patch');
    return src(['./package.json', './' + info.main])
        .pipe(bump({
            version: newVer
        }))
        .pipe(dest('./'))
        .pipe(touch());
};
function prereleasePackageVersion() {
    var newVer = semver.inc(info.version, 'prerelease');
    return src(['./package.json', './' + info.main])
        .pipe(bump({
            version: newVer
        }))
        .pipe(dest('./'))
        .pipe(touch());;
};

function startWatch() {
    watch('./assets/sass/*.scss', css);
    watch('./assets/js/*.js', js);
}

exports.css = css;
exports.js = js;
exports.dev = series(js, css, prereleasePackageVersion);
exports.build = series(js, css, patchPackageVersion);
exports.devversion = prereleasePackageVersion;
exports.buildversion = patchPackageVersion;
exports.default = startWatch;
