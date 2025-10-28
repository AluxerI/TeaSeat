


const [activeItemIndex,setActiveItemIndex] = React.useState<number>(0);
  const [interval,setInterval] = React.useState<number|null>(timeInterval);
  const containerRef = useRef<HTMLDivElement>(null);
    const containerSize = useSize(containerRef);

    useInterval(()=>{
        if(activeItemIndex === items.length -1 )
        {
          setActiveItemIndex(0);
          containerRef.current?.scrollTo({
            left:0,
            top:0
          })
        }
        else{
          setActiveItemIndex(activeItemIndex+1);
        }
    },interval!);

    const handleClickItem = (item:T,index:number ) => {
      setInterval(null);
      setActiveItemIndex(index);
    }

    useEffect(()=>{
      if(interval === null){
        setInterval(timeInterval)
      }

    },[interval]);

    useEffect(()=>{
      const currentDOM = containerRef.current?.children[activeItemIndex] as HTMLElement;
      if(containerSize?.height && currentDOM.offsetHeight)
    })