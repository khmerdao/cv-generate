import Encore from '@symfony/webpack-encore';

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore.setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('app', './assets/app.ts')
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .configureBabel((config) => {
        config.plugins.push(['polyfill-corejs3', { method: 'usage-global', version: '3.49' }]);
    })
    .enableStimulusBridge('./assets/controllers.json')
    .enableReactPreset()
    .enableTypeScriptLoader((options) => {
        // Type-checking is run separately (`npm run typecheck`); tsconfig has noEmit.
        options.transpileOnly = true;
    })
    .enablePostCssLoader();

export default await Encore.getWebpackConfig();
