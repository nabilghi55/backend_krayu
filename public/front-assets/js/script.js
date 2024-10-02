// function previewGambar(){
//     const gambar = document.querySelector('#gambar');
//     const gambarPreview = document.querySelector('.gambar-preview');

//     const oFReader = new FileReader();
//     oFReader.readAsDataURL(gambar.files[0]);

//     oFReader.onload = function(oFREvent) {
//         gambarPreview.src = oFREvent.target.result;
//     }
// }
function previewVideo() {
    const videoInput = document.querySelector('#video');
    const videoPreview = document.querySelector('.video-preview');

    const videoFile = videoInput.files[0];
    const videoURL = URL.createObjectURL(videoFile);

    videoPreview.src = videoURL;
}

// Panggil fungsi ini ketika input berubah
const videoInput = document.querySelector('#video');
videoInput.addEventListener('change', function() {
    previewVideo();
});

function previewGambar() {
    var input = document.getElementById('slider_image');
    var preview = document.querySelector('.gambar-preview');
    var file = input.files[0];
    var reader = new FileReader();

    reader.onload = function() {
        preview.src = reader.result;
    };

    if (file) {
        reader.readAsDataURL(file);
    }
}
