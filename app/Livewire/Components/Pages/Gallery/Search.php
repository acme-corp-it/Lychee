<?php

namespace App\Livewire\Components\Pages\Gallery;

use App\Actions\Search\AlbumSearch;
use App\Actions\Search\PhotoSearch;
use App\Enum\ColumnSortingPhotoType;
use App\Enum\OrderSortingType;
use App\Livewire\DTO\AlbumFlags;
use App\Livewire\DTO\AlbumRights;
use App\Livewire\DTO\Layouts;
use App\Livewire\DTO\PhotoFlags;
use App\Livewire\DTO\SessionFlags;
use App\Models\Album as ModelsAlbum;
use App\Models\Configs;
use App\Models\Photo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use function Safe\base64_decode;

/**
 * Search page.
 */
class Search extends BaseAlbumComponent
{
	use WithPagination;
	private AlbumSearch $albumSearch;
	private PhotoSearch $photoSearch;

	/** @var LengthAwarePaginator<Photo> */
	private LengthAwarePaginator $photos;
	/** @var Collection<ModelsAlbum> */
	private Collection $albums;

	#[Locked]
	#[Url(history: true)]
	public string $urlQuery = '';
	public string $searchQuery = ''; // ! Wired

	/** @var string[] */
	protected array $terms = [];

	#[Locked]
	public int $search_minimum_length_required;

	public function boot(): void
	{
		$this->layouts = new Layouts();
		$this->albumSearch = resolve(AlbumSearch::class);
		$this->photoSearch = resolve(PhotoSearch::class);
		$this->photos = new LengthAwarePaginator([], 0, 200);
		$this->albums = collect([]);
		$this->num_albums = 0;
		$this->num_photos = 0;
		$this->search_minimum_length_required = Configs::getValueAsInt('search_minimum_length_required');
	}

	public function mount(string $albumId = ''): void
	{
		if (!Auth::check() && !Configs::getValueAsBool('search_public')) {
			redirect(route('livewire-gallery'));
		}

		$this->rights = AlbumRights::make(null);

		$this->flags = new AlbumFlags();
		$this->photoFlags = new PhotoFlags(
			can_autoplay: true,
			can_rotate: Configs::getValueAsBool('editor_enabled'),
			can_edit: false,
		);
		$this->sessionFlags = SessionFlags::get();
		$this->flags->is_base_album = false;
		$this->flags->is_accessible = true;
	}

	/**
	 * Whenever searchQuery is updated, we recompute the urlQuery.
	 *
	 * @return void
	 */
	public function updatedSearchQuery(): void
	{
		$this->urlQuery = base64_encode($this->searchQuery);
	}

	/**
	 * Render component.
	 *
	 * @return View
	 */
	public function render(): View
	{
		$this->searchQuery = base64_decode($this->urlQuery, true);
		$this->terms = explode(' ', str_replace(
			['\\', '%', '_'],
			['\\\\', '\\%', '\\_'],
			$this->searchQuery
		));

		if (strlen($this->searchQuery) >= $this->search_minimum_length_required) {
			/** @var LengthAwarePaginator<Photo> $photoResults */
			/** @disregard P1013 Undefined method withQueryString() (stupid intelephense) */
			$photoResults = $this->photoSearch
				->sqlQuery($this->terms)
				->orderBy(ColumnSortingPhotoType::TAKEN_AT->value, OrderSortingType::ASC->value)
				->paginate(Configs::getValueAsInt('search_pagination_limit'))
				->withQueryString();
			$this->photos = $photoResults;
			/** @var Collection<ModelsAlbum> $albumResults */
			$albumResults = $this->albumSearch->queryAlbums($this->terms);
			$this->albums = $albumResults;
			$this->num_albums = $this->albums->count();
			$this->num_photos = $this->photos->count();
			$this->albumId = '';
			$this->photoId = '';
		}

		return view('livewire.pages.gallery.search');
	}

	/**
	 * Return the photos.
	 *
	 * @return Collection<Photo>
	 */
	public function getPhotosProperty(): Collection
	{
		return collect($this->photos->items());
	}

	public function getAlbumsProperty(): ?Collection
	{
		return $this->albums;
	}

	public function getAlbumIDsProperty(): array
	{
		return $this->albums->map(fn ($v, $k) => $v->id)->all();
	}

	// For now, simple
	public function getBackProperty(): string
	{
		return route('livewire-gallery');
	}

	// For now, simple
	public function getTitleProperty(): string
	{
		return __('lychee.SEARCH');
	}

	public function getAlbumFormattedProperty(): null
	{
		return null;
	}

	public function getNoImagesAlbumsMessageProperty(): string
	{
		return strlen($this->searchQuery) < 3 ? '' : 'No results';
	}
}
