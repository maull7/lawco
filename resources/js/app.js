import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import pdfViewer from './pdf-viewer';
import regulationPicker from './regulation-picker';
import sectorSelector from './sector-selector';
import sectorFilter from './sector-filter';
import { complianceBarChart, scoreDistChart, parseComparisonChart } from './charts';

window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.data('pdfViewer', pdfViewer);
Alpine.data('regulationPicker', regulationPicker);
Alpine.data('sectorSelector', sectorSelector);
Alpine.data('sectorFilter', sectorFilter);
Alpine.data('complianceBarChart', complianceBarChart);
Alpine.data('scoreDistChart', scoreDistChart);
Alpine.data('parseComparisonChart', parseComparisonChart);
Alpine.start();
