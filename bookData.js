import fs from 'fs';

const bookData = {
    info: {},

    getData: function () {
        const jsonData = fs.readFileSync('book.json', 'utf-8');
        try {
            this.info = JSON.parse(jsonData);
        } catch (e) {
            console.error('Error: ' + e.message);
            process.exit(1);
        }
    },

    checkData: function () {
        const {src: src_folder, dst: dst_folder, title: book_title, author, publisher} = this.info;

        if (!src_folder) {
            console.error('Error: "src" folder is missing in the book.json file.');
            process.exit(1);
        } else if (!fs.existsSync(src_folder) || !fs.statSync(src_folder).isDirectory()) {
            console.error('Error: "src" folder does not exist or is not a directory.');
            process.exit(1);
        }

        if (!dst_folder) {
            console.error('Error: "dst" folder is missing in the book.json file.');
            process.exit(1);
        }

        if (!fs.existsSync(dst_folder) && !fs.mkdirSync(dst_folder, {recursive: true}) && !fs.statSync(dst_folder).isDirectory()) {
            console.error(`Error: Directory "${dst_folder}" was not created`);
            process.exit(1);
        }

        if (!book_title) {
            console.error('Error: "title" is missing in the book.json file.');
            process.exit(1);
        }

        if (!author) {
            console.error('Error: "author" is missing in the book.json file.');
            process.exit(1);
        }

        if (!publisher) {
            console.error('Error: "publisher" is missing in the book.json file.');
            process.exit(1);
        }
    },
};

export default bookData;
