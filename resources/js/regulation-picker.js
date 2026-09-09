export default function regulationPicker(open, categories = [], initial = {}) {
    return {
        query: '',
        open: { ...open },
        sectorId: String(initial.sectorId || ''),
        categoryId: String(initial.categoryId || ''),
        subCategoryId: String(initial.subCategoryId || ''),
        init() {
            this.$watch('query', () => this.expandAll());
        },
        expandAll() {
            this.open = { ...open };
        },
        changeSector() {
            this.categoryId = '';
            this.subCategoryId = '';
            this.expandAll();
        },
        changeCategory() {
            this.subCategoryId = '';
            this.expandAll();
        },
        get availableCategories() {
            return categories.filter(category => !this.sectorId || category.sectorId === this.sectorId);
        },
        get availableSubCategories() {
            return this.availableCategories
                .filter(category => !this.categoryId || category.id === this.categoryId)
                .flatMap(category => category.subCategories);
        },
        get visibleRegulations() {
            const query = this.query.trim().toLowerCase();
            return this.availableCategories
                .filter(category => !this.categoryId || category.id === this.categoryId)
                .flatMap(category => category.regulations.map(regulation => ({ ...regulation, categoryId: category.id })))
                .filter(regulation => (!this.subCategoryId || regulation.subCategoryIds.includes(this.subCategoryId))
                    && (!query || regulation.search.includes(query)));
        },
        hasCategory(id) {
            return this.visibleRegulations.some(regulation => regulation.categoryId === id);
        },
        hasRegulation(id) {
            return this.visibleRegulations.some(regulation => regulation.id === id);
        },
    };
}
